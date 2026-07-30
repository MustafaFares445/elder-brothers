<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OtpService $otp)
    {
    }

    public function register(Request $request)
    {
        if (! config('elder.registration_enabled', true)) {
            return $this->error('REGISTRATION_DISABLED', __('api.registration_disabled'), 503);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
            'fcm_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $phone = $this->normalizePhone($data['phone']);

        if (User::query()->where('phone', $phone)->exists()) {
            return $this->error('PHONE_ALREADY_REGISTERED', __('api.phone_already_registered'), 409);
        }

        $user = User::query()->create([
            'full_name' => $data['full_name'],
            'phone' => $phone,
            'password' => $data['password'],
            'status' => 'inactive',
            'is_admin' => false,
        ]);

        return $this->success([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'status' => $user->status,
            'activation_required' => true,
        ], __('api.account_pending_activation'), 'REGISTRATION_PENDING_ACTIVATION', 201);
    }

    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'purpose' => ['required', Rule::in(['password_reset'])],
        ]);

        $phone = $this->normalizePhone($data['phone']);
        User::query()->where('phone', $phone)->firstOrFail();
        $otp = $this->otp->issue($phone, 'password_reset');

        return $this->success([
            'phone' => $phone,
            'purpose' => 'password_reset',
            'otp_expires_at' => $otp->expires_at->toIso8601String(),
            'resend_after_seconds' => config('elder.otp.resend_seconds'),
        ], __('api.verification_sent'), 'OTP_RESENT');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
            'fcm_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = User::query()->where('phone', $this->normalizePhone($data['phone']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->error('INVALID_CREDENTIALS', __('api.invalid_credentials'), 422);
        }

        if ($user->status === 'inactive') {
            return $this->error('ACCOUNT_INACTIVE', __('api.account_inactive'), 403);
        }

        if ($user->status !== 'active') {
            return $this->error('ACCOUNT_SUSPENDED', __('api.account_suspended'), 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;
        $this->upsertDevice($user, $data);

        return $this->success([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => new UserResource($user),
        ], __('api.logged_in'), 'LOGGED_IN');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        if ($request->boolean('remove_fcm_token', true) && $request->filled('device_id')) {
            $request->user()->devices()->where('device_id', $request->string('device_id'))->delete();
        }

        return $this->success(null, __('api.logged_out'), 'LOGGED_OUT');
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string']]);
        $phone = $this->normalizePhone($data['phone']);

        User::query()->where('phone', $phone)->firstOrFail();

        $otp = $this->otp->issue($phone, 'password_reset');

        return $this->success([
            'phone' => $phone,
            'verification_required' => true,
            'otp_expires_at' => $otp->expires_at->toIso8601String(),
            'resend_after_seconds' => config('elder.otp.resend_seconds'),
        ], __('api.verification_sent'), 'PASSWORD_RESET_OTP_SENT');
    }

    public function verifyResetOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
        ]);

        $phone = $this->normalizePhone($data['phone']);
        $this->otp->verify($phone, 'password_reset', $data['otp']);

        $token = Str::random(64);
        cache()->put('password-reset:'.hash('sha256', $token), $phone, now()->addMinutes(15));

        return $this->success([
            'reset_token' => $token,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ], __('api.otp_verified'), 'RESET_OTP_VERIFIED');
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $phone = cache()->pull('password-reset:'.hash('sha256', $data['reset_token']));

        if (! $phone || $phone !== $this->normalizePhone($data['phone'])) {
            return $this->error('RESET_TOKEN_INVALID', __('api.reset_token_invalid'), 422);
        }

        User::query()->where('phone', $phone)->firstOrFail()->update(['password' => $data['password']]);

        return $this->success(null, __('api.password_reset'), 'PASSWORD_RESET');
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone);
    }

    private function upsertDevice(User $user, array $data): void
    {
        if (empty($data['device_id']) || empty($data['fcm_token'])) {
            return;
        }

        UserDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $data['device_id']],
            [
                'fcm_token' => $data['fcm_token'],
                'platform' => $data['platform'] ?? 'android',
                'last_seen_at' => now(),
            ],
        );
    }
}
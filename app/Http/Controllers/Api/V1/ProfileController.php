<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\UserDevice;
use App\Models\UserPreference;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        $user = $request->user();
        $preferences = $user->preferences()->firstOrCreate([], [
            'locale' => 'ar',
            'smart_notifications' => true,
            'download_quality' => 'auto',
        ]);

        return $this->success([
            'user' => new UserResource($user),
            'preferences' => $preferences->only(['locale', 'smart_notifications', 'download_quality']),
            'status' => $user->status,
            'active_subscription_count' => $user->subscriptions()->where('status', 'active')->count(),
            'expired_subscription_count' => $user->subscriptions()->where('status', 'expired')->count(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'phone' => ['sometimes', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($request->user()->id)],
        ]);

        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/[^\d+]/', '', $data['phone']);
        }

        $request->user()->update($data);

        return $this->success([
            'user' => new UserResource($request->user()->fresh()),
        ], __('api.profile_updated'), 'PROFILE_UPDATED');
    }

    public function avatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $path = $data['avatar']->store('avatars', 'public');
        $request->user()->update(['avatar_path' => $path]);

        return $this->success([
            'avatar_url' => asset('storage/'.$path),
            'user' => new UserResource($request->user()->fresh()),
        ], __('api.avatar_updated'), 'AVATAR_UPDATED');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return $this->error('CURRENT_PASSWORD_INCORRECT', __('api.current_password_incorrect'), 422);
        }

        $request->user()->update(['password' => $data['password']]);

        return $this->success(null, __('api.password_updated'), 'PASSWORD_UPDATED');
    }

    public function preferences(Request $request)
    {
        $data = $request->validate([
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            'smart_notifications' => ['sometimes', 'boolean'],
            'download_quality' => ['sometimes', Rule::in(['auto', 'hd', 'sd'])],
        ]);
        $preferences = UserPreference::updateOrCreate(['user_id' => $request->user()->id], $data);

        return $this->success(
            $preferences->only(['locale', 'smart_notifications', 'download_quality']),
            __('api.preferences_updated'),
            'PREFERENCES_UPDATED',
        );
    }

    public function storeDevice(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'fcm_token' => ['required', 'string', 'max:2048'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:30'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);
        $device = UserDevice::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_id' => $data['device_id']],
            $data + ['last_seen_at' => now()],
        );

        return $this->success(
            $device->only(['id', 'device_id', 'platform', 'notifications_enabled', 'last_seen_at']),
            __('api.device_registered'),
            'DEVICE_REGISTERED',
        );
    }

    public function destroyDevice(Request $request, UserDevice $device)
    {
        abort_unless($device->user_id === $request->user()->id, 404);
        $device->delete();

        return $this->success(null, __('api.device_removed'), 'DEVICE_REMOVED');
    }
}
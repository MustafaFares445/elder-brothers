<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResources;
use App\Models\CourseSubscription;
use App\Models\QrRedemption;
use App\Models\SubscriptionQrCode;
use App\Notifications\SubscriptionActivatedNotification;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrSubscriptionController extends Controller
{
    use ApiResponse;

    public function preview(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
        ]);

        $qrCode = $this->find($data['code']);

        if (! $qrCode) {
            return $this->error('QR_INVALID', __('api.qr_invalid'), 422);
        }

        if ($error = $this->stateError($qrCode)) {
            return $error;
        }

        $existing = $request->user()
            ->subscriptions()
            ->where('course_id', $qrCode->course_id)
            ->latest()
            ->first();

        $subscriptionExpiresAt = $qrCode->subscription_duration_days
            ? now()->addDays($qrCode->subscription_duration_days)
            : null;

        return $this->success([
            'valid' => true,
            'course' => CatalogResources::course($qrCode->course, $request->user()),
            'subscription_duration_days' => $qrCode->subscription_duration_days,
            'code_expires_at' => $qrCode->expires_at?->toIso8601String(),
            'subscription_expires_at' => $subscriptionExpiresAt?->toIso8601String(),
            'already_subscribed' => $existing?->isActive() ?? false,
            'existing_subscription' => CatalogResources::subscription($existing),
            'redemption_policy' => $existing?->isActive() ? 'extend' : 'create',
            'confirmation_required' => true,
            'single_use' => true,
        ], __('api.qr_valid'), 'QR_VALID');
    }

    public function redeem(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'confirm' => ['accepted'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $qrCode = SubscriptionQrCode::query()
                ->where('code_hash', hash('sha256', $data['code']))
                ->lockForUpdate()
                ->first();

            if (! $qrCode) {
                return $this->error('QR_INVALID', __('api.qr_invalid'), 422);
            }

            if ($error = $this->stateError($qrCode)) {
                return $error;
            }

            $existing = $request->user()
                ->subscriptions()
                ->where('course_id', $qrCode->course_id)
                ->latest()
                ->first();

            $previousExpiresAt = $existing?->expires_at;
            $baseDate = $existing?->isActive() && $existing->expires_at?->isFuture()
                ? $existing->expires_at
                : now();
            $newExpiresAt = $qrCode->subscription_duration_days
                ? $baseDate->copy()->addDays($qrCode->subscription_duration_days)
                : null;

            $subscription = CourseSubscription::query()->updateOrCreate([
                'user_id' => $request->user()->id,
                'course_id' => $qrCode->course_id,
            ], [
                'source' => 'qr',
                'starts_at' => $existing?->starts_at ?? now(),
                'expires_at' => $newExpiresAt,
                'revoked_at' => null,
                'status' => 'active',
            ]);

            $redemption = QrRedemption::query()->create([
                'subscription_qr_code_id' => $qrCode->id,
                'user_id' => $request->user()->id,
                'course_subscription_id' => $subscription->id,
                'redeemed_at' => now(),
                'ip_address' => $request->ip(),
                'device_id' => $data['device_id'] ?? null,
            ]);

            $qrCode->forceFill([
                'redemptions_count' => 1,
                'status' => 'exhausted',
            ])->save();

            $request->user()->notify(new SubscriptionActivatedNotification($subscription));

            return $this->success([
                'subscription' => CatalogResources::subscription($subscription, 0),
                'course' => CatalogResources::course($qrCode->course, $request->user()),
                'redemption_id' => $redemption->id,
                'was_extended' => (bool) $existing,
                'previous_expires_at' => $previousExpiresAt?->toIso8601String(),
                'new_expires_at' => $newExpiresAt?->toIso8601String(),
            ], __('api.subscription_activated'), 'SUBSCRIPTION_ACTIVATED', $existing ? 200 : 201);
        });
    }

    private function find(string $rawCode): ?SubscriptionQrCode
    {
        return SubscriptionQrCode::query()
            ->with('course')
            ->where('code_hash', hash('sha256', $rawCode))
            ->first();
    }

    private function stateError(SubscriptionQrCode $qrCode)
    {
        if ($qrCode->status === 'disabled') {
            return $this->error('QR_DISABLED', __('api.qr_disabled'), 422);
        }

        if ($qrCode->status === 'exhausted' || $qrCode->redemptions_count >= 1) {
            return $this->error('QR_LIMIT_REACHED', __('api.qr_limit_reached'), 409);
        }

        if ($qrCode->expires_at?->isPast()) {
            return $this->error('QR_EXPIRED', __('api.qr_expired'), 422);
        }

        if ($qrCode->starts_at?->isFuture()) {
            return $this->error('QR_NOT_STARTED', __('api.qr_not_started'), 422);
        }

        return null;
    }
}

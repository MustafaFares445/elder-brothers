<?php

namespace App\Services;

use App\Models\CourseSubscription;
use App\Models\QrRedemption;
use App\Models\SubscriptionQrCode;
use App\Models\User;
use App\Jobs\SendSubscriptionActivatedPush;
use App\Notifications\SubscriptionActivatedNotification;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QrSubscriptionService
{
    public function findByRawCode(string $code): SubscriptionQrCode
    {
        $qr = SubscriptionQrCode::query()
            ->with('course.subject.academicYear')
            ->where('code_hash', hash('sha256', trim($code)))
            ->first();

        if (! $qr) {
            throw new HttpException(409, 'QR_INVALID');
        }

        return $qr;
    }

    public function ensureRedeemable(SubscriptionQrCode $qr): void
    {
        if ($qr->status === 'disabled') {
            throw new HttpException(409, 'QR_DISABLED');
        }

        if ($qr->expires_at?->isPast() || $qr->status === 'expired') {
            throw new HttpException(409, 'QR_EXPIRED');
        }

        if ($qr->starts_at?->isFuture()) {
            throw new HttpException(409, 'QR_NOT_STARTED');
        }

        if ($qr->status === 'exhausted' || ($qr->max_redemptions !== null && $qr->redemptions_count >= $qr->max_redemptions)) {
            throw new HttpException(409, 'QR_LIMIT_REACHED');
        }
    }

    public function calculateExpiry(SubscriptionQrCode $qr, ?CourseSubscription $existing = null): ?\Illuminate\Support\Carbon
    {
        if ($qr->subscription_duration_days === null) {
            return null;
        }

        $start = $existing?->expires_at?->isFuture() ? $existing->expires_at->copy() : now();

        return $start->addDays($qr->subscription_duration_days);
    }

    public function redeem(User $user, string $code, ?string $deviceId, ?string $ipAddress): array
    {
        return DB::transaction(function () use ($user, $code, $deviceId, $ipAddress): array {
            $qr = SubscriptionQrCode::query()
                ->where('code_hash', hash('sha256', trim($code)))
                ->lockForUpdate()
                ->first();

            if (! $qr) {
                throw new HttpException(409, 'QR_INVALID');
            }

            $this->ensureRedeemable($qr);

            if (QrRedemption::query()->where('subscription_qr_code_id', $qr->id)->where('user_id', $user->id)->exists()) {
                throw new HttpException(409, 'QR_ALREADY_USED');
            }

            $existing = CourseSubscription::query()
                ->where('user_id', $user->id)
                ->where('course_id', $qr->course_id)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->latest('expires_at')
                ->first();

            $previousExpiry = $existing?->expires_at?->copy();
            $newExpiry = $this->calculateExpiry($qr, $existing);
            $wasExtended = $existing !== null;

            if ($existing) {
                $existing->update([
                    'expires_at' => $newExpiry,
                    'status' => 'active',
                    'revoked_at' => null,
                ]);
                $subscription = $existing->fresh('course');
            } else {
                $subscription = CourseSubscription::create([
                    'user_id' => $user->id,
                    'course_id' => $qr->course_id,
                    'source' => 'qr',
                    'starts_at' => now(),
                    'expires_at' => $newExpiry,
                    'status' => 'active',
                ])->load('course');
            }

            $redemption = QrRedemption::create([
                'subscription_qr_code_id' => $qr->id,
                'user_id' => $user->id,
                'course_subscription_id' => $subscription->id,
                'redeemed_at' => now(),
                'ip_address' => $ipAddress,
                'device_id' => $deviceId,
            ]);

            $qr->increment('redemptions_count');
            $qr->refresh();

            if ($qr->max_redemptions !== null && $qr->redemptions_count >= $qr->max_redemptions) {
                $qr->update(['status' => 'exhausted']);
            }

            $user->notify(new SubscriptionActivatedNotification($subscription));
            SendSubscriptionActivatedPush::dispatch($subscription->id);

            return [
                'subscription' => $subscription,
                'redemption_id' => $redemption->id,
                'was_extended' => $wasExtended,
                'previous_expires_at' => $previousExpiry,
                'new_expires_at' => $newExpiry,
            ];
        }, 3);
    }
}

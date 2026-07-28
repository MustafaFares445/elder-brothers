<?php

namespace App\Services;

use App\Models\CourseSubscription;
use App\Notifications\SubscriptionActivatedNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function grant(
        int $userId,
        int $courseId,
        ?CarbonInterface $expiresAt = null,
        string $source = 'admin',
    ): CourseSubscription {
        return DB::transaction(function () use ($userId, $courseId, $expiresAt, $source): CourseSubscription {
            $subscription = CourseSubscription::query()
                ->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->lockForUpdate()
                ->first();

            if ($subscription) {
                $subscription->forceFill([
                    'source' => $source,
                    'starts_at' => $subscription->starts_at ?? now(),
                    'expires_at' => $expiresAt,
                    'revoked_at' => null,
                    'status' => 'active',
                ])->save();
            } else {
                $subscription = CourseSubscription::query()->create([
                    'user_id' => $userId,
                    'course_id' => $courseId,
                    'source' => $source,
                    'starts_at' => now(),
                    'expires_at' => $expiresAt,
                    'status' => 'active',
                ]);
            }

            $subscription->loadMissing(['user', 'course']);
            $subscription->user->notify(new SubscriptionActivatedNotification($subscription));

            return $subscription;
        });
    }

    public function extend(CourseSubscription $subscription, int $days): CourseSubscription
    {
        $base = $subscription->expires_at?->isFuture()
            ? $subscription->expires_at
            : now();

        $subscription->forceFill([
            'expires_at' => $base->copy()->addDays($days),
            'revoked_at' => null,
            'status' => 'active',
        ])->save();

        return $subscription->refresh();
    }

    public function revoke(CourseSubscription $subscription): CourseSubscription
    {
        $subscription->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
        ])->save();

        return $subscription->refresh();
    }

    public function reactivate(CourseSubscription $subscription, ?CarbonInterface $expiresAt = null): CourseSubscription
    {
        $subscription->forceFill([
            'status' => 'active',
            'revoked_at' => null,
            'starts_at' => $subscription->starts_at ?? now(),
            'expires_at' => $expiresAt ?? $subscription->expires_at,
        ])->save();

        return $subscription->refresh();
    }
}

<?php

namespace App\Jobs;

use App\Models\CourseSubscription;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSubscriptionActivatedPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $subscriptionId)
    {
        $this->afterCommit();
    }

    public function handle(FcmService $fcm): void
    {
        $subscription = CourseSubscription::with(['user', 'course'])->find($this->subscriptionId);

        if (! $subscription) {
            return;
        }

        $locale = $subscription->user->preferences?->locale ?? 'ar';
        $courseTitle = $subscription->course->localizedTitle($locale);

        $fcm->sendToUser(
            $subscription->user,
            $locale === 'ar' ? 'تم تفعيل اشتراكك' : 'Subscription activated',
            $locale === 'ar'
                ? "تم تفعيل اشتراكك في دورة {$courseTitle}."
                : "Your subscription to {$courseTitle} is now active.",
            ['type' => 'subscription_activated', 'course_id' => $subscription->course_id],
        );
    }
}

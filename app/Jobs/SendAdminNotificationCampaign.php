<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\AdminBroadcastNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

class SendAdminNotificationCampaign implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly array $data)
    {
    }

    public function handle(): void
    {
        $payload = [
            'type' => 'admin_broadcast',
            'title_ar' => $this->data['title_ar'],
            'title_en' => $this->data['title_en'],
            'body_ar' => $this->data['body_ar'],
            'body_en' => $this->data['body_en'],
            'action_type' => ($this->data['action_type'] ?? 'none') === 'none' ? null : $this->data['action_type'],
            'action_id' => $this->data['action_id'] ?? null,
            'action_url' => $this->data['action_url'] ?? null,
        ];

        $this->recipientQuery()->chunkById(200, function ($users) use ($payload): void {
            foreach ($users as $user) {
                $user->notify(new AdminBroadcastNotification($payload));
            }
        });
    }

    private function recipientQuery(): Builder
    {
        $query = User::query()
            ->where('is_admin', false)
            ->where('status', 'active');

        return match ($this->data['audience'] ?? 'all_active') {
            'students' => $query->whereIn('id', $this->data['student_ids'] ?? []),
            'course' => $query->whereHas('subscriptions', fn (Builder $query): Builder => $query
                ->where('course_id', $this->data['course_id'])
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))),
            'expiring' => $query->whereHas('subscriptions', fn (Builder $query): Builder => $query
                ->where('status', 'active')
                ->whereBetween('expires_at', [now(), now()->addDays((int) ($this->data['expiring_days'] ?? 7))])),
            default => $query,
        };
    }
}

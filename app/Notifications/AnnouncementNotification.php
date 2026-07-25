<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $title,
        public array $body,
        public ?string $actionType = null,
        public ?int $actionId = null,
        public ?string $imageUrl = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement',
            'title' => $this->title,
            'body' => $this->body,
            'action_type' => $this->actionType,
            'action_id' => $this->actionId,
            'image_url' => $this->imageUrl,
        ];
    }
}

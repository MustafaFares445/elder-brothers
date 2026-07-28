<?php

namespace App\Filament\Resources\CourseSubscriptions\Pages;

use App\Filament\Resources\CourseSubscriptions\CourseSubscriptionResource;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCourseSubscription extends CreateRecord
{
    protected static string $resource = CourseSubscriptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SubscriptionService::class)->grant(
            userId: (int) $data['user_id'],
            courseId: (int) $data['course_id'],
            expiresAt: filled($data['expires_at'] ?? null)
                ? Carbon::parse($data['expires_at'])
                : null,
            source: $data['source'] ?? 'admin',
        );
    }
}

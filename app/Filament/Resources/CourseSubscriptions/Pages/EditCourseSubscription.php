<?php

namespace App\Filament\Resources\CourseSubscriptions\Pages;

use App\Filament\Resources\CourseSubscriptions\CourseSubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseSubscription extends EditRecord
{
    protected static string $resource = CourseSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

<?php

namespace App\Filament\Resources\CourseSubscriptions\Pages;

use App\Filament\Resources\CourseSubscriptions\CourseSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseSubscriptions extends ListRecords
{
    protected static string $resource = CourseSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

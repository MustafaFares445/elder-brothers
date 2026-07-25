<?php

namespace App\Filament\Resources\CourseVideos\Pages;

use App\Filament\Resources\CourseVideos\CourseVideoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseVideos extends ListRecords
{
    protected static string $resource = CourseVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

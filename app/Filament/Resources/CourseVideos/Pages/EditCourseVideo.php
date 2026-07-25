<?php

namespace App\Filament\Resources\CourseVideos\Pages;

use App\Filament\Resources\CourseVideos\CourseVideoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseVideo extends EditRecord
{
    protected static string $resource = CourseVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

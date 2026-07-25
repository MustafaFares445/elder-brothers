<?php

namespace App\Filament\Resources\CourseVideos\Pages;

use App\Filament\Resources\CourseVideos\CourseVideoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseVideo extends CreateRecord
{
    protected static string $resource = CourseVideoResource::class;
}

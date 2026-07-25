<?php

namespace App\Filament\Resources\CourseFiles\Pages;

use App\Filament\Resources\CourseFiles\CourseFileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseFile extends CreateRecord
{
    protected static string $resource = CourseFileResource::class;
}

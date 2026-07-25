<?php

namespace App\Filament\Resources\CourseFiles\Pages;

use App\Filament\Resources\CourseFiles\CourseFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseFiles extends ListRecords
{
    protected static string $resource = CourseFileResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

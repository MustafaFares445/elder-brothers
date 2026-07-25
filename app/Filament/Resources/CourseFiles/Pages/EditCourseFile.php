<?php

namespace App\Filament\Resources\CourseFiles\Pages;

use App\Filament\Resources\CourseFiles\CourseFileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseFile extends EditRecord
{
    protected static string $resource = CourseFileResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

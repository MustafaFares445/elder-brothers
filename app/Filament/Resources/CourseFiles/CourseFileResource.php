<?php

namespace App\Filament\Resources\CourseFiles;

use App\Filament\Resources\CourseFiles\Pages;
use App\Models\Course;
use App\Models\CourseFile;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourseFileResource extends Resource
{
    protected static ?string $model = CourseFile::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')
                ->options(fn () => Course::query()->get()->mapWithKeys(
                    fn (Course $course) => [$course->id => $course->localized('title')],
                ))
                ->required()
                ->searchable(),
            TextInput::make('title.ar')->required(),
            TextInput::make('title.en')->required(),
            FileUpload::make('file_path')
                ->disk(fn () => config('filesystems.course_media', 'local'))
                ->directory('courses/pdfs')
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(102400)
                ->storeFileNamesIn('original_name')
                ->required(),
            TextInput::make('sort_order')->integer()->required(),
            Toggle::make('is_downloadable'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title.ar')->label(__('dashboard.fields.title')),
                TextColumn::make('course.title.ar')->label(__('dashboard.fields.course')),
                TextColumn::make('original_name')->label(__('dashboard.fields.original_name')),
                IconColumn::make('is_downloadable')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseFiles::route('/'),
            'create' => Pages\CreateCourseFile::route('/create'),
            'edit' => Pages\EditCourseFile::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\CourseVideos;

use App\Filament\Resources\CourseVideos\Pages;
use App\Models\Course;
use App\Models\CourseVideo;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourseVideoResource extends Resource
{
    protected static ?string $model = CourseVideo::class;

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
            TextInput::make('lesson_label.ar'),
            TextInput::make('lesson_label.en'),
            TextInput::make('source_path')->required(),
            TextInput::make('hls_manifest_path'),
            TextInput::make('duration_seconds')->integer()->required(),
            TextInput::make('sort_order')->integer()->required(),
            Toggle::make('is_preview'),
            Toggle::make('is_downloadable'),
            Select::make('status')->options([
                'processing' => __('dashboard.statuses.processing'),
                'ready' => __('dashboard.statuses.ready'),
                'failed' => __('dashboard.statuses.failed'),
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title.ar')->label(__('dashboard.fields.title')),
                TextColumn::make('course.title.ar')->label(__('dashboard.fields.course')),
                TextColumn::make('duration_seconds')->label(__('dashboard.fields.duration_seconds')),
                IconColumn::make('is_preview')->boolean(),
                TextColumn::make('status')->badge(),
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
            'index' => Pages\ListCourseVideos::route('/'),
            'create' => Pages\CreateCourseVideo::route('/create'),
            'edit' => Pages\EditCourseVideo::route('/{record}/edit'),
        ];
    }
}

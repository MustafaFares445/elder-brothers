<?php

namespace App\Filament\Resources\CourseVideos;

use App\Filament\Resources\CourseVideos\Pages;
use App\Models\CourseVideo;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')->relationship('course', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->localizedTitle('en'))->required()->searchable(),
TextInput::make('title.ar')->label('Arabic title')->required(),
TextInput::make('title.en')->label('English title')->required(),
TextInput::make('lesson_label.ar')->label('Arabic lesson label'),
TextInput::make('lesson_label.en')->label('English lesson label'),
TextInput::make('thumbnail_url')->url(),
TextInput::make('source_path')->required(),
TextInput::make('hls_manifest_path'),
TextInput::make('duration_seconds')->numeric()->required(),
TextInput::make('sort_order')->numeric()->required(),
Toggle::make('is_preview'),
Toggle::make('is_downloadable'),
Select::make('status')->options(['processing' => 'Processing', 'ready' => 'Ready', 'failed' => 'Failed'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title.en')->label('Title')->searchable(),
TextColumn::make('course.title.en')->label('Course'),
TextColumn::make('duration_seconds')->numeric()->sortable(),
TextColumn::make('sort_order')->sortable(),
IconColumn::make('is_preview')->boolean(),
IconColumn::make('is_downloadable')->boolean(),
TextColumn::make('status')->badge(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
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

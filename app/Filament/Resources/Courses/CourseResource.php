<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Resources\Courses\Pages;
use App\Models\Course;
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

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('subject_id')->relationship('subject', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->localizedTitle('en'))->required()->searchable(),
TextInput::make('slug')->required()->unique(ignoreRecord: true),
TextInput::make('title.ar')->label('Arabic title')->required(),
TextInput::make('title.en')->label('English title')->required(),
Textarea::make('short_description.ar')->label('Arabic summary'),
Textarea::make('short_description.en')->label('English summary'),
RichEditor::make('description.ar')->label('Arabic description')->required(),
RichEditor::make('description.en')->label('English description')->required(),
TextInput::make('thumbnail_url')->url(),
TextInput::make('hero_url')->url(),
Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])->required(),
Toggle::make('is_featured'),
DateTimePicker::make('published_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title.ar')->label('Arabic title')->searchable(),
TextColumn::make('title.en')->label('English title')->searchable(),
TextColumn::make('subject.title.en')->label('Subject'),
TextColumn::make('status')->badge(),
IconColumn::make('is_featured')->boolean(),
TextColumn::make('videos_count')->counts('videos'),
TextColumn::make('files_count')->counts('files'),
TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}

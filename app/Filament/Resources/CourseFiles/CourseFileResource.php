<?php

namespace App\Filament\Resources\CourseFiles;

use App\Filament\Resources\CourseFiles\Pages;
use App\Models\CourseFile;
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

class CourseFileResource extends Resource
{
    protected static ?string $model = CourseFile::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')->relationship('course', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->localizedTitle('en'))->required()->searchable(),
TextInput::make('title.ar')->label('Arabic title')->required(),
TextInput::make('title.en')->label('English title')->required(),
TextInput::make('file_path'),
TextInput::make('external_url')->url(),
TextInput::make('original_name')->required(),
TextInput::make('mime_type')->required(),
TextInput::make('extension')->required(),
TextInput::make('size_bytes')->numeric()->required(),
TextInput::make('sort_order')->numeric()->required(),
Toggle::make('is_downloadable')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title.en')->label('Title')->searchable(),
TextColumn::make('course.title.en')->label('Course'),
TextColumn::make('extension')->badge(),
TextColumn::make('size_bytes')->numeric(),
TextColumn::make('sort_order')->sortable(),
IconColumn::make('is_downloadable')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
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

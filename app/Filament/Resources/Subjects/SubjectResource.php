<?php

namespace App\Filament\Resources\Subjects;

use App\Filament\Resources\Subjects\Pages;
use App\Models\Subject;
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

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')->relationship('academicYear', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->localized('title', 'en'))->required()->searchable(),
TextInput::make('title.ar')->label('Arabic title')->required(),
TextInput::make('title.en')->label('English title')->required(),
TextInput::make('image_url')->url(),
TextInput::make('sort_order')->numeric()->required(),
Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title.ar')->label('Arabic title')->searchable(),
TextColumn::make('title.en')->label('English title')->searchable(),
TextColumn::make('academicYear.title.en')->label('Academic year'),
TextColumn::make('courses_count')->counts('courses'),
TextColumn::make('sort_order')->sortable(),
IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjects::route('/'),
            'create' => Pages\CreateSubject::route('/create'),
            'edit' => Pages\EditSubject::route('/{record}/edit'),
        ];
    }
}

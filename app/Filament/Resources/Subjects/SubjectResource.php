<?php

namespace App\Filament\Resources\Subjects;

use App\Filament\Resources\Subjects\Pages;
use App\Models\AcademicYear;
use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.subjects');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.subject');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.subjects');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Select::make('academic_year_id')
                        ->label(__('dashboard.fields.academic_year'))
                        ->options(fn () => AcademicYear::query()
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn (AcademicYear $year) => [
                                $year->id => $year->localized('title', 'ar'),
                            ]))
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('title.ar')
                        ->label(__('dashboard.fields.title'))
                        ->required()
                        ->maxLength(191),
                    TextInput::make('image_url')
                        ->label(__('dashboard.fields.image_url'))
                        ->url()
                        ->maxLength(2048)
                        ->columnSpanFull(),
                    TextInput::make('sort_order')
                        ->label(__('dashboard.fields.sort_order'))
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Toggle::make('is_active')
                        ->label(__('dashboard.fields.active'))
                        ->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['academicYear'])
                ->withCount('courses'))
            ->columns([
                TextColumn::make('title.ar')
                    ->label(__('dashboard.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academicYear.title.ar')
                    ->label(__('dashboard.fields.academic_year'))
                    ->searchable(),
                TextColumn::make('courses_count')
                    ->label(__('dashboard.resources.courses'))
                    ->numeric(),
                TextColumn::make('sort_order')
                    ->label(__('dashboard.fields.sort_order'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('dashboard.fields.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('academic_year_id')
                    ->label(__('dashboard.fields.academic_year'))
                    ->relationship('academicYear', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn (AcademicYear $record) => $record->localized('title', 'ar'),
                    ),
                TernaryFilter::make('is_active')
                    ->label(__('dashboard.fields.active')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggle_active')
                    ->label(fn (Subject $record) => $record->is_active
                        ? __('dashboard.actions.deactivate')
                        : __('dashboard.actions.activate'))
                    ->color(fn (Subject $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Subject $record) => $record->update([
                        'is_active' => ! $record->is_active,
                    ])),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function canDelete($record): bool
    {
        return ! $record->courses()->exists();
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

<?php

namespace App\Filament\Resources\AcademicYears;

use App\Filament\Resources\AcademicYears\Pages;
use App\Models\AcademicYear;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AcademicYearResource extends Resource
{
    protected static ?string $model = AcademicYear::class;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.academic_years');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.academic_year');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.academic_years');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextInput::make('title.ar')
                        ->label(__('dashboard.fields.title'))
                        ->required()
                        ->maxLength(191),
                    TextInput::make('subtitle.ar')
                        ->label(__('dashboard.fields.subtitle'))
                        ->maxLength(255),
                    Select::make('icon')
                        ->label(__('dashboard.fields.icon'))
                        ->options([
                            'school' => 'مدرسة',
                            'menu_book' => 'كتاب',
                            'history_edu' => 'تعليم',
                            'workspace_premium' => 'تميز',
                            'auto_stories' => 'محتوى',
                        ])
                        ->searchable(),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('subjects'))
            ->columns([
                TextColumn::make('title.ar')
                    ->label(__('dashboard.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subjects_count')
                    ->label(__('dashboard.resources.subjects'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('dashboard.fields.sort_order'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('dashboard.fields.active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('dashboard.fields.active')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggle_active')
                    ->label(fn (AcademicYear $record) => $record->is_active
                        ? __('dashboard.actions.deactivate')
                        : __('dashboard.actions.activate'))
                    ->color(fn (AcademicYear $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (AcademicYear $record) => $record->update([
                        'is_active' => ! $record->is_active,
                    ])),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function canDelete($record): bool
    {
        return ! $record->subjects()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicYears::route('/'),
            'create' => Pages\CreateAcademicYear::route('/create'),
            'edit' => Pages\EditAcademicYear::route('/{record}/edit'),
        ];
    }
}

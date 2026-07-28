<?php

namespace App\Filament\Resources\ContentPages;

use App\Filament\Resources\ContentPages\Pages;
use App\Models\ContentPage;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContentPageResource extends Resource
{
    protected static ?string $model = ContentPage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.content_pages');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.content_page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.content_pages');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label(__('dashboard.fields.slug'))
                ->datalist(['privacy-policy', 'terms', 'help'])
                ->in(['privacy-policy', 'terms', 'help'])
                ->required()
                ->unique(ignoreRecord: true)
                ->disabledOn('edit')
                ->dehydrated(),
            Tabs::make('translations')->tabs([
                Tab::make(__('dashboard.fields.arabic'))->schema([
                    TextInput::make('title.ar')->label(__('dashboard.fields.title'))->required(),
                    RichEditor::make('content.ar')->label(__('dashboard.fields.content'))->required()->columnSpanFull(),
                ]),
                Tab::make(__('dashboard.fields.english'))->schema([
                    TextInput::make('title.en')->label(__('dashboard.fields.title'))->required(),
                    RichEditor::make('content.en')->label(__('dashboard.fields.content'))->required()->columnSpanFull(),
                ]),
            ])->columnSpanFull(),
            Toggle::make('is_active')->label(__('dashboard.fields.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->label(__('dashboard.fields.slug'))->searchable(),
                TextColumn::make('title.ar')->label(__('dashboard.fields.title'))->searchable(),
                TextColumn::make('title.en')->label(__('dashboard.fields.english_title'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label(__('dashboard.fields.active'))->boolean(),
                TextColumn::make('updated_at')->label(__('dashboard.fields.updated_at'))->dateTime()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('dashboard.fields.active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('slug');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentPages::route('/'),
            'create' => Pages\CreateContentPage::route('/create'),
            'edit' => Pages\EditContentPage::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}

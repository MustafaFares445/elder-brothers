<?php

namespace App\Filament\Resources\Notifications;

use App\Filament\Resources\Notifications\Pages;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

class NotificationResource extends Resource
{
    protected static ?string $model = DatabaseNotification::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.notifications');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.notifications');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('notifiable.full_name')->label(__('dashboard.fields.user')),
                TextEntry::make('type')->label(__('dashboard.fields.type'))->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextEntry::make('title')->label(__('dashboard.fields.title'))->state(fn (DatabaseNotification $record): string => $record->data['title_ar'] ?? $record->data['title'] ?? ''),
                TextEntry::make('body')->label(__('dashboard.fields.message'))->state(fn (DatabaseNotification $record): string => $record->data['body_ar'] ?? $record->data['body'] ?? '')->columnSpanFull(),
                KeyValueEntry::make('data')->label(__('dashboard.fields.data'))->columnSpanFull(),
                TextEntry::make('read_at')->label(__('dashboard.fields.read_at'))->dateTime(),
                TextEntry::make('created_at')->label(__('dashboard.fields.created_at'))->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('notifiable.full_name')->label(__('dashboard.fields.user'))->searchable(),
                TextColumn::make('type')->label(__('dashboard.fields.type'))->formatStateUsing(fn (string $state): string => class_basename($state))->badge(),
                TextColumn::make('data.title_ar')->label(__('dashboard.fields.title'))->limit(50),
                IconColumn::make('read_at')->label(__('dashboard.fields.read'))->boolean(),
                TextColumn::make('created_at')->label(__('dashboard.fields.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('unread')->label(__('dashboard.fields.unread'))->query(fn (Builder $query): Builder => $query->whereNull('read_at')),
                Filter::make('read')->label(__('dashboard.fields.read'))->query(fn (Builder $query): Builder => $query->whereNotNull('read_at')),
            ])
            ->recordUrl(fn (DatabaseNotification $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'view' => Pages\ViewNotification::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}

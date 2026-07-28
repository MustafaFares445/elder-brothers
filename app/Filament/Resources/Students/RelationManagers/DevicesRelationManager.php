<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('dashboard.fields.device_info');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device_id')
                    ->label(__('dashboard.fields.device_id'))
                    ->formatStateUsing(fn (string $state) => str($state)->mask('*', 4, -4)),
                TextColumn::make('platform')
                    ->label(__('dashboard.fields.platform'))
                    ->badge(),
                TextColumn::make('app_version')
                    ->label(__('dashboard.fields.app_version')),
                IconColumn::make('notifications_enabled')
                    ->label(__('dashboard.fields.notifications_enabled'))
                    ->boolean(),
                TextColumn::make('last_seen_at')
                    ->label(__('dashboard.fields.last_seen_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_seen_at', 'desc');
    }
}

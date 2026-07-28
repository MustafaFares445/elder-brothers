<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Models\CourseSubscription;
use App\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('dashboard.resources.subscriptions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.title.ar')->label(__('dashboard.fields.course')),
                TextColumn::make('source')->label(__('dashboard.fields.source'))->badge()->formatStateUsing(fn (string $state): string => __('dashboard.statuses.'.$state)),
                TextColumn::make('status')->label(__('dashboard.fields.status'))->badge()->formatStateUsing(fn (string $state): string => __('dashboard.statuses.'.$state)),
                TextColumn::make('starts_at')->label(__('dashboard.fields.starts_at'))->dateTime(),
                TextColumn::make('expires_at')->label(__('dashboard.fields.expires_at'))->dateTime(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('dashboard.actions.revoke'))
                    ->color('danger')
                    ->visible(fn (CourseSubscription $record): bool => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(fn (CourseSubscription $record) => app(SubscriptionService::class)->revoke($record)),
                Action::make('reactivate')
                    ->label(__('dashboard.actions.reactivate'))
                    ->color('success')
                    ->visible(fn (CourseSubscription $record): bool => $record->status !== 'active')
                    ->action(fn (CourseSubscription $record) => app(SubscriptionService::class)->reactivate($record)),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

<?php

namespace App\Filament\Resources\SupportRequests;

use App\Filament\Resources\SupportRequests\Pages;
use App\Models\SupportRequest;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.support_requests');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.support_request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.support_requests');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\Select::make('status')
                ->label(__('dashboard.fields.status'))
                ->options([
                    'open' => __('dashboard.statuses.open'),
                    'in_progress' => __('dashboard.statuses.in_progress'),
                    'resolved' => __('dashboard.statuses.resolved'),
                    'closed' => __('dashboard.statuses.closed'),
                ])
                ->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('dashboard.resources.support_request'))->schema([
                TextEntry::make('reference')->label(__('dashboard.fields.reference'))->copyable(),
                TextEntry::make('user.full_name')->label(__('dashboard.fields.user')),
                TextEntry::make('subject')->label(__('dashboard.fields.subject')),
                TextEntry::make('category')->label(__('dashboard.fields.category'))->badge()->formatStateUsing(fn (string $state): string => __('dashboard.categories.'.$state)),
                TextEntry::make('status')->label(__('dashboard.fields.status'))->badge()->formatStateUsing(fn (string $state): string => __('dashboard.statuses.'.$state)),
                TextEntry::make('message')->label(__('dashboard.fields.message'))->columnSpanFull(),
                KeyValueEntry::make('device_info')->label(__('dashboard.fields.device_info'))->columnSpanFull(),
                TextEntry::make('created_at')->label(__('dashboard.fields.created_at'))->dateTime(),
                TextEntry::make('updated_at')->label(__('dashboard.fields.updated_at'))->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label(__('dashboard.fields.reference'))->searchable()->copyable(),
                TextColumn::make('user.full_name')->label(__('dashboard.fields.user'))->searchable(),
                TextColumn::make('subject')->label(__('dashboard.fields.subject'))->searchable()->limit(45),
                TextColumn::make('category')->label(__('dashboard.fields.category'))->badge()->formatStateUsing(fn (string $state): string => __('dashboard.categories.'.$state)),
                TextColumn::make('status')->label(__('dashboard.fields.status'))->badge()->formatStateUsing(fn (string $state): string => __('dashboard.statuses.'.$state)),
                TextColumn::make('created_at')->label(__('dashboard.fields.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('dashboard.fields.status'))->options([
                    'open' => __('dashboard.statuses.open'),
                    'in_progress' => __('dashboard.statuses.in_progress'),
                    'resolved' => __('dashboard.statuses.resolved'),
                    'closed' => __('dashboard.statuses.closed'),
                ]),
                SelectFilter::make('category')->label(__('dashboard.fields.category'))->options([
                    'technical' => __('dashboard.categories.technical'),
                    'subscription' => __('dashboard.categories.subscription'),
                    'content' => __('dashboard.categories.content'),
                    'account' => __('dashboard.categories.account'),
                    'other' => __('dashboard.categories.other'),
                ]),
                Filter::make('has_attachment')->label(__('dashboard.fields.has_attachment'))->query(fn (Builder $query): Builder => $query->whereNotNull('attachment_path')),
            ])
            ->recordActions([
                Action::make('viewAttachment')
                    ->label(__('dashboard.actions.view_attachment'))
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn (SupportRequest $record): bool => filled($record->attachment_path))
                    ->url(fn (SupportRequest $record): string => Storage::disk(config('filesystems.private'))->temporaryUrl($record->attachment_path, now()->addMinutes(10)))
                    ->openUrlInNewTab(),
                Action::make('start')
                    ->label(__('dashboard.actions.start_progress'))
                    ->icon('heroicon-o-play')
                    ->visible(fn (SupportRequest $record): bool => $record->status === 'open')
                    ->action(fn (SupportRequest $record) => $record->update(['status' => 'in_progress'])),
                Action::make('resolve')
                    ->label(__('dashboard.actions.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (SupportRequest $record): bool => in_array($record->status, ['open', 'in_progress'], true))
                    ->action(fn (SupportRequest $record) => $record->update(['status' => 'resolved'])),
                Action::make('close')
                    ->label(__('dashboard.actions.close'))
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (SupportRequest $record): bool => $record->status !== 'closed')
                    ->requiresConfirmation()
                    ->action(fn (SupportRequest $record) => $record->update(['status' => 'closed'])),
                Action::make('reopen')
                    ->label(__('dashboard.actions.reopen'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (SupportRequest $record): bool => in_array($record->status, ['resolved', 'closed'], true))
                    ->action(fn (SupportRequest $record) => $record->update(['status' => 'open'])),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportRequests::route('/'),
            'view' => Pages\ViewSupportRequest::route('/{record}'),
            'edit' => Pages\EditSupportRequest::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}

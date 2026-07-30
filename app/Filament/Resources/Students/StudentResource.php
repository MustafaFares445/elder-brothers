<?php

namespace App\Filament\Resources\Students;

use App\Filament\Resources\Students\Pages;
use App\Filament\Resources\Students\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\Students\RelationManagers\SubscriptionsRelationManager;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.students');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.students');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.student');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.students');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_admin', false)
            ->withCount('subscriptions');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('full_name')
                ->label(__('dashboard.fields.full_name'))
                ->required()
                ->maxLength(100),
            TextInput::make('phone')
                ->label(__('dashboard.fields.phone'))
                ->tel()
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true),
            TextInput::make('email')
                ->label(__('dashboard.fields.email'))
                ->email()
                ->maxLength(191)
                ->unique(ignoreRecord: true),
            Toggle::make('account_active')
                ->label(__('dashboard.fields.account_active'))
                ->helperText(__('dashboard.messages.account_activation_help'))
                ->inline(false),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextEntry::make('full_name')->label(__('dashboard.fields.full_name')),
                    TextEntry::make('phone')->label(__('dashboard.fields.phone')),
                    TextEntry::make('email')->label(__('dashboard.fields.email')),
                    TextEntry::make('status')
                        ->label(__('dashboard.fields.status'))
                        ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                        ->badge(),
                    TextEntry::make('last_login_at')
                        ->label(__('dashboard.fields.last_login_at'))
                        ->dateTime(),
                    TextEntry::make('created_at')
                        ->label(__('dashboard.fields.created_at'))
                        ->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('dashboard.fields.full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('dashboard.fields.phone'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('dashboard.fields.email'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('account_active')
                    ->label(__('dashboard.fields.account_active'))
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title($state
                                ? __('dashboard.messages.account_activated')
                                : __('dashboard.messages.account_deactivated'))
                            ->success()
                            ->send();
                    }),
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('subscriptions_count')
                    ->label(__('dashboard.resources.subscriptions'))
                    ->numeric(),
                TextColumn::make('last_login_at')
                    ->label(__('dashboard.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('dashboard.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->options([
                        'inactive' => __('dashboard.statuses.inactive'),
                        'active' => __('dashboard.statuses.active'),
                        'suspended' => __('dashboard.statuses.suspended'),
                    ]),
                Filter::make('has_active_subscription')
                    ->label(__('dashboard.widgets.active_subscriptions'))
                    ->query(fn (Builder $query) => $query->whereHas(
                        'subscriptions',
                        fn (Builder $subscription) => $subscription
                            ->where('status', 'active')
                            ->whereNull('revoked_at')
                            ->where(fn (Builder $date) => $date
                                ->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now())),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('revoke_tokens')
                    ->label(__('dashboard.actions.revoke_tokens'))
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->tokens()->delete()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DevicesRelationManager::class,
            SubscriptionsRelationManager::class,
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
<?php

namespace App\Filament\Resources\Students;

use App\Filament\Resources\Students\Pages;
use App\Filament\Resources\Students\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\Students\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Resources\Students\RelationManagers\VideoProgressRelationManager;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
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
            ->withCount('subscriptions')
            ->withSum('videoProgress', 'watched_seconds');
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
            DateTimePicker::make('phone_verified_at')
                ->label(__('dashboard.fields.phone_verified'))
                ->seconds(false),
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
                    IconEntry::make('phone_verified_at')
                        ->label(__('dashboard.fields.phone_verified'))
                        ->boolean(),
                    TextEntry::make('last_login_at')
                        ->label(__('dashboard.fields.last_login_at'))
                        ->dateTime(),
                    TextEntry::make('created_at')
                        ->label(__('dashboard.fields.created_at'))
                        ->dateTime(),
                    TextEntry::make('suspension_reason')
                        ->label(__('dashboard.fields.suspension_reason'))
                        ->columnSpanFull(),
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
                    ->toggleable(),
                IconColumn::make('phone_verified_at')
                    ->label(__('dashboard.fields.phone_verified'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('subscriptions_count')
                    ->label(__('dashboard.resources.subscriptions'))
                    ->numeric(),
                TextColumn::make('video_progress_sum_watched_seconds')
                    ->label(__('dashboard.widgets.watch_hours'))
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 3600, 1)),
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
                TernaryFilter::make('status')
                    ->label(__('dashboard.fields.active'))
                    ->trueLabel(__('dashboard.statuses.active'))
                    ->falseLabel(__('dashboard.statuses.suspended'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('status', 'active'),
                        false: fn (Builder $query) => $query->where('status', 'suspended'),
                    ),
                TernaryFilter::make('phone_verified_at')
                    ->label(__('dashboard.fields.phone_verified'))
                    ->nullable(),
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
                Action::make('suspend')
                    ->label(__('dashboard.actions.suspend'))
                    ->color('danger')
                    ->visible(fn (User $record) => $record->status === 'active')
                    ->form([
                        Textarea::make('reason')
                            ->label(__('dashboard.fields.suspension_reason'))
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data): void {
                        $record->forceFill([
                            'status' => 'suspended',
                            'suspended_at' => now(),
                            'suspension_reason' => $data['reason'] ?? null,
                        ])->save();

                        $record->tokens()->delete();

                        Notification::make()
                            ->title(__('dashboard.actions.suspend'))
                            ->success()
                            ->send();
                    }),
                Action::make('activate')
                    ->label(__('dashboard.actions.activate'))
                    ->color('success')
                    ->visible(fn (User $record) => $record->status === 'suspended')
                    ->action(fn (User $record) => $record->forceFill([
                        'status' => 'active',
                        'suspended_at' => null,
                        'suspension_reason' => null,
                    ])->save()),
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
            VideoProgressRelationManager::class,
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

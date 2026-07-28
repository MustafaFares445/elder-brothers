<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.admins');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.admin');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.admins');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_admin', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('is_admin')->default(true),
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
                ->required()
                ->maxLength(191)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label(__('dashboard.fields.password'))
                ->password()
                ->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->minLength(8),
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
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('last_login_at')
                    ->label(__('dashboard.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('dashboard.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('suspend')
                    ->label(__('dashboard.actions.suspend'))
                    ->color('danger')
                    ->visible(fn (User $record) => $record->status === 'active' && auth()->id() !== $record->id)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $activeAdmins = User::query()
                            ->where('is_admin', true)
                            ->where('status', 'active')
                            ->count();

                        if ($activeAdmins <= 1) {
                            Notification::make()
                                ->title(__('dashboard.validation.last_admin'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->forceFill([
                            'status' => 'suspended',
                            'suspended_at' => now(),
                        ])->save();

                        $record->tokens()->delete();
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
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

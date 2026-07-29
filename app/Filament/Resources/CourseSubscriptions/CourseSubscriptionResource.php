<?php

namespace App\Filament\Resources\CourseSubscriptions;

use App\Filament\Resources\CourseSubscriptions\Pages;
use App\Models\CourseSubscription;
use App\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CourseSubscriptionResource extends Resource
{
    protected static ?string $model = CourseSubscription::class;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.subscriptions');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.subscriptions');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.subscriptions');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'course']))
            ->columns([
                TextColumn::make('user.full_name')
                    ->label(__('dashboard.fields.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course.title.ar')
                    ->label(__('dashboard.fields.course'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('starts_at')
                    ->label(__('dashboard.fields.starts_at'))
                    ->formatStateUsing(fn ($state) => $state
                        ? $state->locale('ar')->translatedFormat('d F Y')
                        : 'غير محدد'),
                TextColumn::make('expires_at')
                    ->label(__('dashboard.fields.expires_at'))
                    ->formatStateUsing(fn ($state) => $state
                        ? $state->locale('ar')->translatedFormat('d F Y')
                        : 'بدون تاريخ انتهاء')
                    ->sortable(),
                TextColumn::make('days_remaining')
                    ->label(__('dashboard.fields.days_remaining'))
                    ->state(function (CourseSubscription $record): string {
                        if (! $record->expires_at) {
                            return 'غير محدد';
                        }

                        if ($record->expires_at->isPast()) {
                            return 'منتهي';
                        }

                        $days = (int) now()->startOfDay()->diffInDays(
                            $record->expires_at->copy()->startOfDay(),
                        );

                        return $days === 0 ? 'ينتهي اليوم' : $days.' يوم';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'منتهي' => 'danger',
                        $state === 'ينتهي اليوم' => 'danger',
                        str_contains($state, 'يوم') && (int) $state <= 7 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->options([
                        'active' => __('dashboard.statuses.active'),
                        'expired' => __('dashboard.statuses.expired'),
                        'revoked' => __('dashboard.statuses.revoked'),
                    ]),
                Filter::make('expires_soon')
                    ->label(__('dashboard.widgets.expiring_subscriptions'))
                    ->query(fn (Builder $query) => $query
                        ->where('status', 'active')
                        ->whereBetween('expires_at', [now(), now()->addDays(7)])),
            ])
            ->recordActions([
                Action::make('extend')
                    ->label(__('dashboard.actions.extend'))
                    ->form([
                        TextInput::make('days')
                            ->label('عدد أيام التمديد')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->required()
                            ->default(30),
                    ])
                    ->action(fn (CourseSubscription $record, array $data) => app(SubscriptionService::class)
                        ->extend($record, (int) $data['days'])),
                Action::make('revoke')
                    ->label(__('dashboard.actions.revoke'))
                    ->color('danger')
                    ->visible(fn (CourseSubscription $record) => $record->status !== 'revoked')
                    ->requiresConfirmation()
                    ->action(fn (CourseSubscription $record) => app(SubscriptionService::class)->revoke($record)),
                Action::make('reactivate')
                    ->label(__('dashboard.actions.reactivate'))
                    ->color('success')
                    ->visible(fn (CourseSubscription $record) => $record->status !== 'active')
                    ->action(fn (CourseSubscription $record) => app(SubscriptionService::class)->reactivate($record)),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListCourseSubscriptions::route('/'),
        ];
    }
}

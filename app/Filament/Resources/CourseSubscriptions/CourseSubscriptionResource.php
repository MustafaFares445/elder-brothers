<?php

namespace App\Filament\Resources\CourseSubscriptions;

use App\Filament\Resources\CourseSubscriptions\Pages;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label(__('dashboard.fields.user'))
                ->options(fn () => User::query()
                    ->where('is_admin', false)
                    ->where('status', 'active')
                    ->orderBy('full_name')
                    ->pluck('full_name', 'id'))
                ->required()
                ->searchable(),
            Select::make('course_id')
                ->label(__('dashboard.fields.course'))
                ->options(fn () => Course::query()
                    ->whereIn('status', ['draft', 'published'])
                    ->get()
                    ->mapWithKeys(fn (Course $course) => [
                        $course->id => $course->localized('title'),
                    ]))
                ->required()
                ->searchable(),
            Select::make('source')
                ->label(__('dashboard.fields.source'))
                ->options([
                    'admin' => __('dashboard.statuses.admin'),
                    'qr' => __('dashboard.statuses.qr'),
                ])
                ->default('admin')
                ->required(),
            DateTimePicker::make('starts_at')
                ->label(__('dashboard.fields.starts_at'))
                ->default(now())
                ->required(),
            DateTimePicker::make('expires_at')
                ->label(__('dashboard.fields.expires_at')),
            Select::make('status')
                ->label(__('dashboard.fields.status'))
                ->options([
                    'active' => __('dashboard.statuses.active'),
                    'expired' => __('dashboard.statuses.expired'),
                    'revoked' => __('dashboard.statuses.revoked'),
                ])
                ->default('active')
                ->required(),
        ]);
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
                TextColumn::make('source')
                    ->label(__('dashboard.fields.source'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge(),
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
                    ->dateTime(),
                TextColumn::make('expires_at')
                    ->label(__('dashboard.fields.expires_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('days_remaining')
                    ->label(__('dashboard.fields.days_remaining'))
                    ->getStateUsing(fn (CourseSubscription $record) => $record->expires_at
                        ? max(0, now()->diffInDays($record->expires_at, false))
                        : '∞'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->options([
                        'active' => __('dashboard.statuses.active'),
                        'expired' => __('dashboard.statuses.expired'),
                        'revoked' => __('dashboard.statuses.revoked'),
                    ]),
                SelectFilter::make('source')
                    ->label(__('dashboard.fields.source'))
                    ->options([
                        'admin' => __('dashboard.statuses.admin'),
                        'qr' => __('dashboard.statuses.qr'),
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

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseSubscriptions::route('/'),
            'create' => Pages\CreateCourseSubscription::route('/create'),
        ];
    }
}

<?php

namespace App\Filament\Resources\SubscriptionQrCodes;

use App\Filament\Resources\SubscriptionQrCodes\Pages;
use App\Models\Course;
use App\Models\PlatformSetting;
use App\Models\SubscriptionQrCode;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SubscriptionQrCodeResource extends Resource
{
    protected static ?string $model = SubscriptionQrCode::class;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.subscriptions');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.qr_codes');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.qr_code');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.qr_codes');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')
                ->label(__('dashboard.fields.course'))
                ->options(fn () => Course::query()
                    ->where('status', 'published')
                    ->get()
                    ->mapWithKeys(fn (Course $course) => [
                        $course->id => $course->localized('title'),
                    ]))
                ->required()
                ->searchable(),
            TextInput::make('label')
                ->label(__('dashboard.fields.label'))
                ->required()
                ->maxLength(191),
            TextInput::make('raw_code')
                ->label(__('dashboard.fields.raw_code'))
                ->default(fn () => 'ELDER-'.Str::upper(Str::random(32)))
                ->disabled()
                ->dehydrated()
                ->required()
                ->minLength(16)
                ->maxLength(2048)
                ->visibleOn('create')
                ->helperText(__('dashboard.messages.raw_qr_once')),
            TextInput::make('code_hint')
                ->label(__('dashboard.fields.code_hint'))
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit'),
            DateTimePicker::make('starts_at')
                ->label(__('dashboard.fields.starts_at'))
                ->default(now()),
            DateTimePicker::make('expires_at')
                ->label(__('dashboard.fields.expires_at')),
            TextInput::make('subscription_duration_days')
                ->label(__('dashboard.fields.subscription_duration_days'))
                ->integer()
                ->minValue(1)
                ->default(fn (): int => (int) PlatformSetting::value('default_qr_duration_days', 365)),
            TextInput::make('max_redemptions')
                ->label(__('dashboard.fields.max_redemptions'))
                ->integer()
                ->minValue(1)
                ->default(fn (): int => (int) PlatformSetting::value('default_qr_max_redemptions', 1)),
            Select::make('status')
                ->label(__('dashboard.fields.status'))
                ->options([
                    'active' => __('dashboard.statuses.active'),
                    'disabled' => __('dashboard.statuses.disabled'),
                    'exhausted' => __('dashboard.statuses.exhausted'),
                    'expired' => __('dashboard.statuses.expired'),
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['course', 'creator']))
            ->columns([
                TextColumn::make('label')
                    ->label(__('dashboard.fields.label'))
                    ->searchable(),
                TextColumn::make('course.title.ar')
                    ->label(__('dashboard.fields.course'))
                    ->searchable(),
                TextColumn::make('code_hint')
                    ->label(__('dashboard.fields.code_hint'))
                    ->copyable(),
                TextColumn::make('redemptions_count')
                    ->label(__('dashboard.fields.redemptions_count'))
                    ->numeric(),
                TextColumn::make('max_redemptions')
                    ->label(__('dashboard.fields.max_redemptions'))
                    ->numeric(),
                TextColumn::make('subscription_duration_days')
                    ->label(__('dashboard.fields.subscription_duration_days'))
                    ->numeric(),
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge(),
                TextColumn::make('expires_at')
                    ->label(__('dashboard.fields.expires_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('creator.full_name')
                    ->label(__('dashboard.fields.created_by'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->options([
                        'active' => __('dashboard.statuses.active'),
                        'disabled' => __('dashboard.statuses.disabled'),
                        'exhausted' => __('dashboard.statuses.exhausted'),
                        'expired' => __('dashboard.statuses.expired'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('disable')
                    ->label(__('dashboard.actions.deactivate'))
                    ->color('danger')
                    ->visible(fn (SubscriptionQrCode $record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(fn (SubscriptionQrCode $record) => $record->update(['status' => 'disabled'])),
                Action::make('activate')
                    ->label(__('dashboard.actions.activate'))
                    ->color('success')
                    ->visible(fn (SubscriptionQrCode $record) => $record->status === 'disabled'
                        && (! $record->expires_at || $record->expires_at->isFuture()))
                    ->action(fn (SubscriptionQrCode $record) => $record->update(['status' => 'active'])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canDelete($record): bool
    {
        return $record->redemptions_count === 0;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionQrCodes::route('/'),
            'create' => Pages\CreateSubscriptionQrCode::route('/create'),
            'edit' => Pages\EditSubscriptionQrCode::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\SubscriptionQrCodes;

use App\Filament\Resources\SubscriptionQrCodes\Pages;
use App\Models\Course;
use App\Models\SubscriptionQrCode;
use App\Services\SubscriptionQrCodeService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            Section::make()
                ->schema([
                    Select::make('course_id')
                        ->label(__('dashboard.fields.course'))
                        ->options(fn () => Course::query()
                            ->where('status', 'published')
                            ->get()
                            ->mapWithKeys(fn (Course $course) => [
                                $course->id => $course->localized('title', 'ar'),
                            ]))
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('label')
                        ->label(__('dashboard.fields.label'))
                        ->required()
                        ->maxLength(191),
                    TextInput::make('raw_code')
                        ->label(__('dashboard.fields.raw_code'))
                        ->default(fn () => app(SubscriptionQrCodeService::class)->generateRawCode())
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->visibleOn('create')
                        ->helperText('يستخدم الكود مرة واحدة فقط. يمكنك تحديد تاريخ انتهاء صلاحيته.'),
                    TextInput::make('code_encrypted')
                        ->label(__('dashboard.fields.raw_code'))
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit')
                        ->suffixAction(
                            Action::make('view_barcode')
                                ->label('عرض الباركود')
                                ->icon('heroicon-o-qr-code')
                                ->url(fn (?SubscriptionQrCode $record): ?string => $record?->barcodeUrl(420))
                                ->openUrlInNewTab(),
                        ),
                    DateTimePicker::make('expires_at')
                        ->label(__('dashboard.fields.expires_at'))
                        ->default(fn () => now()->addDays(2))
                        ->minDate(now())
                        ->seconds(false)
                        ->native(false)
                        ->required()
                        ->helperText('بعد هذا التاريخ لن يقبل التطبيق استخدام الكود.'),
                    TextInput::make('subscription_duration_days')
                        ->label(__('dashboard.fields.subscription_duration_days'))
                        ->integer()
                        ->minValue(1)
                        ->default(365)
                        ->required(),
                ])
                ->columns(2)
                ->columnSpanFull(),
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
                TextColumn::make('code_encrypted')
                    ->label(__('dashboard.fields.raw_code'))
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'غير متوفر للكود القديم')
                    ->copyable()
                    ->wrap(),
                TextColumn::make('redemptions_count')
                    ->label(__('dashboard.fields.redemptions_count'))
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? 'تم الاستخدام' : 'غير مستخدم')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'gray' : 'success'),
                TextColumn::make('subscription_duration_days')
                    ->label(__('dashboard.fields.subscription_duration_days'))
                    ->formatStateUsing(fn (int $state): string => $state.' يوم'),
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge(),
                TextColumn::make('expires_at')
                    ->label(__('dashboard.fields.expires_at'))
                    ->formatStateUsing(fn ($state) => $state
                        ? $state->locale('ar')->translatedFormat('d F Y، H:i')
                        : 'غير محدد')
                    ->sortable(),
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
                Action::make('view_barcode')
                    ->label('عرض الباركود')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (SubscriptionQrCode $record): ?string => $record->barcodeUrl(520))
                    ->openUrlInNewTab()
                    ->visible(fn (SubscriptionQrCode $record): bool => filled($record->code_encrypted)),
                EditAction::make()
                    ->visible(fn (SubscriptionQrCode $record): bool => $record->redemptions_count === 0),
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
                        && $record->expires_at?->isFuture())
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
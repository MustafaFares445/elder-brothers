<?php

namespace App\Filament\Resources\QrRedemptions;

use App\Filament\Resources\QrRedemptions\Pages;
use App\Models\QrRedemption;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QrRedemptionResource extends Resource
{
    protected static ?string $model = QrRedemption::class;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.subscriptions');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.qr_redemptions');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.qr_redemption');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.qr_redemptions');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('qrCode.label')->label(__('dashboard.fields.label')),
                TextEntry::make('user.full_name')->label(__('dashboard.fields.user')),
                TextEntry::make('subscription.course.title.ar')->label(__('dashboard.fields.course')),
                TextEntry::make('redeemed_at')->label(__('dashboard.fields.created_at'))->dateTime(),
                TextEntry::make('ip_address')->label(__('dashboard.fields.ip_address')),
                TextEntry::make('device_id')
                    ->label(__('dashboard.fields.device_id'))
                    ->formatStateUsing(fn (?string $state) => $state
                        ? str($state)->mask('*', 4, -4)
                        : null),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'qrCode.course',
                'user',
                'subscription.course',
            ]))
            ->columns([
                TextColumn::make('qrCode.label')
                    ->label(__('dashboard.fields.label'))
                    ->searchable(),
                TextColumn::make('user.full_name')
                    ->label(__('dashboard.fields.user'))
                    ->searchable(),
                TextColumn::make('subscription.course.title.ar')
                    ->label(__('dashboard.fields.course')),
                TextColumn::make('redeemed_at')
                    ->label(__('dashboard.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label(__('dashboard.fields.ip_address')),
                TextColumn::make('device_id')
                    ->label(__('dashboard.fields.device_id'))
                    ->formatStateUsing(fn (?string $state) => $state
                        ? str($state)->mask('*', 4, -4)
                        : null),
            ])
            ->filters([
                Filter::make('today')
                    ->query(fn (Builder $query) => $query->whereDate('redeemed_at', today())),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('redeemed_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
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
            'index' => Pages\ListQrRedemptions::route('/'),
            'view' => Pages\ViewQrRedemption::route('/{record}'),
        ];
    }
}

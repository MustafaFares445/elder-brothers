<?php

namespace App\Filament\Resources\SubscriptionQrCodes;

use App\Filament\Resources\SubscriptionQrCodes\Pages;
use App\Models\SubscriptionQrCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionQrCodeResource extends Resource
{
    protected static ?string $model = SubscriptionQrCode::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')->relationship('course', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->localizedTitle('en'))->required()->searchable(),
TextInput::make('label')->required(),
TextInput::make('code_hash')->label('SHA-256 code hash')->required()->unique(ignoreRecord: true),
TextInput::make('code_hint'),
DateTimePicker::make('starts_at'),
DateTimePicker::make('expires_at'),
TextInput::make('max_redemptions')->numeric(),
TextInput::make('subscription_duration_days')->numeric(),
Select::make('status')->options(['active' => 'Active', 'disabled' => 'Disabled', 'exhausted' => 'Exhausted', 'expired' => 'Expired'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
TextColumn::make('course.title.en')->label('Course'),
TextColumn::make('code_hint'),
TextColumn::make('redemptions_count')->numeric(),
TextColumn::make('max_redemptions')->numeric(),
TextColumn::make('subscription_duration_days')->numeric(),
TextColumn::make('status')->badge(),
TextColumn::make('expires_at')->dateTime(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
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

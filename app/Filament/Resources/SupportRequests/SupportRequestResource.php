<?php

namespace App\Filament\Resources\SupportRequests;

use App\Filament\Resources\SupportRequests\Pages;
use App\Models\SupportRequest;
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

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->relationship('user', 'full_name')->disabled(),
TextInput::make('reference')->disabled(),
TextInput::make('subject')->disabled(),
Textarea::make('message')->disabled(),
Select::make('category')->options(['technical' => 'Technical', 'subscription' => 'Subscription', 'content' => 'Content', 'account' => 'Account', 'other' => 'Other'])->disabled(),
Select::make('status')->options(['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable(),
TextColumn::make('user.full_name')->searchable(),
TextColumn::make('subject')->searchable()->limit(40),
TextColumn::make('category')->badge(),
TextColumn::make('status')->badge(),
TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportRequests::route('/'),
            'create' => Pages\CreateSupportRequest::route('/create'),
            'edit' => Pages\EditSupportRequest::route('/{record}/edit'),
        ];
    }
}

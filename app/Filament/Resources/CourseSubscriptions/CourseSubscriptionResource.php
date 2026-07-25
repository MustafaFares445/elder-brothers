<?php

namespace App\Filament\Resources\CourseSubscriptions;

use App\Filament\Resources\CourseSubscriptions\Pages;
use App\Models\CourseSubscription;
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

class CourseSubscriptionResource extends Resource
{
    protected static ?string $model = CourseSubscription::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->relationship('user', 'full_name')->required()->searchable(),
Select::make('course_id')->relationship('course', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->localizedTitle('en'))->required()->searchable(),
Select::make('source')->options(['qr' => 'QR', 'admin' => 'Admin'])->required()->default('admin'),
DateTimePicker::make('starts_at')->required()->default(now()),
DateTimePicker::make('expires_at'),
DateTimePicker::make('revoked_at'),
Select::make('status')->options(['active' => 'Active', 'expired' => 'Expired', 'revoked' => 'Revoked'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.full_name')->searchable(),
TextColumn::make('course.title.en')->label('Course')->searchable(),
TextColumn::make('source')->badge(),
TextColumn::make('status')->badge(),
TextColumn::make('starts_at')->dateTime(),
TextColumn::make('expires_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseSubscriptions::route('/'),
            'create' => Pages\CreateCourseSubscription::route('/create'),
            'edit' => Pages\EditCourseSubscription::route('/{record}/edit'),
        ];
    }
}

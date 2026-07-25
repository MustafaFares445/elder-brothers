<?php

namespace App\Filament\Resources\SubscriptionQrCodes\Pages;

use App\Filament\Resources\SubscriptionQrCodes\SubscriptionQrCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionQrCodes extends ListRecords
{
    protected static string $resource = SubscriptionQrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

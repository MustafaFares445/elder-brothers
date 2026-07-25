<?php

namespace App\Filament\Resources\SubscriptionQrCodes\Pages;

use App\Filament\Resources\SubscriptionQrCodes\SubscriptionQrCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionQrCode extends EditRecord
{
    protected static string $resource = SubscriptionQrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

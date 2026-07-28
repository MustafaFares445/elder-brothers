<?php

namespace App\Filament\Resources\SubscriptionQrCodes\Pages;

use App\Filament\Resources\SubscriptionQrCodes\SubscriptionQrCodeResource;
use App\Services\SubscriptionQrCodeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSubscriptionQrCode extends CreateRecord
{
    protected static string $resource = SubscriptionQrCodeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$record, $rawCode] = app(SubscriptionQrCodeService::class)->create(
            data: $data,
            rawCode: $data['raw_code'] ?? null,
            createdBy: auth()->id(),
        );

        Notification::make()
            ->title(__('dashboard.messages.raw_qr_once'))
            ->body($rawCode)
            ->success()
            ->persistent()
            ->send();

        return $record;
    }
}

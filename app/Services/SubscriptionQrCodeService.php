<?php

namespace App\Services;

use App\Models\SubscriptionQrCode;
use Illuminate\Support\Str;

class SubscriptionQrCodeService
{
    public function generateRawCode(): string
    {
        return 'ELDER-'.Str::upper(Str::random(32));
    }

    public function create(array $data, ?string $rawCode, ?int $createdBy): array
    {
        $rawCode = trim($rawCode ?: $this->generateRawCode());

        $record = SubscriptionQrCode::query()->create([
            'course_id' => $data['course_id'],
            'label' => $data['label'] ?? null,
            'code_hash' => hash('sha256', $rawCode),
            'code_hint' => Str::mask($rawCode, '*', 6, -4),
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'redemptions_count' => 0,
            'subscription_duration_days' => $data['subscription_duration_days'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_by' => $createdBy,
        ]);

        return [$record, $rawCode];
    }
}

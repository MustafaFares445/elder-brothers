<?php

namespace App\Services;

use App\Models\SubscriptionQrCode;
use Illuminate\Support\Carbon;
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
        $expiresAt = filled($data['expires_at'] ?? null)
            ? Carbon::parse($data['expires_at'])
            : now()->addDays(2);

        $record = SubscriptionQrCode::query()->create([
            'course_id' => $data['course_id'],
            'label' => $data['label'] ?? null,
            'code_hash' => hash('sha256', $rawCode),
            'code_encrypted' => $rawCode,
            'code_hint' => null,
            'starts_at' => now(),
            'expires_at' => $expiresAt,
            'max_redemptions' => 1,
            'redemptions_count' => 0,
            'subscription_duration_days' => $data['subscription_duration_days'] ?? 365,
            'status' => 'active',
            'created_by' => $createdBy,
        ]);

        return [$record, $rawCode];
    }
}
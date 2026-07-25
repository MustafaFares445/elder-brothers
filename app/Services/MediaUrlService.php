<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class MediaUrlService
{
    public function temporary(string $path, ?string $externalUrl = null): array
    {
        $expiresAt = now()->addMinutes(config('services.media.signed_url_ttl_minutes'));

        if ($externalUrl) {
            return ['url' => $externalUrl, 'expires_at' => $expiresAt];
        }

        return [
            'url' => Storage::disk(config('services.media.private_disk'))->temporaryUrl($path, $expiresAt),
            'expires_at' => $expiresAt,
        ];
    }
}

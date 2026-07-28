<?php

namespace App\Services;

use App\Models\CourseVideo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class PrivateCourseMediaService
{
    public function diskName(): string
    {
        return (string) config('filesystems.course_media', 'local');
    }

    public function expiresAt(): CarbonInterface
    {
        return now()->addMinutes((int) config('elder.signed_url_ttl_minutes', 15));
    }

    /**
     * @return array{url:?string,signature:?string,expires_at:?string,is_private:bool}
     */
    public function temporaryMedia(?string $path, ?CarbonInterface $expiresAt = null): array
    {
        if (blank($path)) {
            return $this->emptyPayload();
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $this->metadataFromUrl($path, false);
        }

        $expiresAt ??= $this->expiresAt();
        $url = Storage::disk($this->diskName())->temporaryUrl($path, $expiresAt);

        return $this->metadataFromUrl($url, true, $expiresAt);
    }

    /**
     * @return array{url:string,signature:?string,expires_at:string,is_private:bool}
     */
    public function streamMedia(CourseVideo $video, ?CarbonInterface $expiresAt = null): array
    {
        $expiresAt ??= $this->expiresAt();
        $url = URL::temporarySignedRoute(
            'api.v1.videos.stream',
            $expiresAt,
            ['video' => $video->getRouteKey()],
        );

        return $this->metadataFromUrl($url, true, $expiresAt);
    }

    /**
     * @return array{url:?string,signature:?string,expires_at:?string,is_private:bool}
     */
    private function metadataFromUrl(
        string $url,
        bool $isPrivate,
        ?CarbonInterface $fallbackExpiration = null,
    ): array {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $expiresAt = $fallbackExpiration?->toIso8601String();

        if (isset($query['expires']) && is_numeric($query['expires'])) {
            $expiresAt = now()->setTimestamp((int) $query['expires'])->toIso8601String();
        }

        return [
            'url' => $url,
            'signature' => isset($query['signature']) ? (string) $query['signature'] : null,
            'expires_at' => $expiresAt,
            'is_private' => $isPrivate,
        ];
    }

    /**
     * @return array{url:null,signature:null,expires_at:null,is_private:bool}
     */
    private function emptyPayload(): array
    {
        return [
            'url' => null,
            'signature' => null,
            'expires_at' => null,
            'is_private' => false,
        ];
    }
}

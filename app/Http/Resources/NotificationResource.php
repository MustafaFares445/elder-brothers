<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $data = $this->data;

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? class_basename($this->type),
            'title' => $this->localized($data['title'] ?? '', $locale),
            'body' => $this->localized($data['body'] ?? '', $locale),
            'image_url' => $data['image_url'] ?? null,
            'action_type' => $data['action_type'] ?? null,
            'action_id' => $data['action_id'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function localized(array|string $value, string $locale): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value[$locale] ?? $value[config('app.fallback_locale')] ?? reset($value) ?: '';
    }
}

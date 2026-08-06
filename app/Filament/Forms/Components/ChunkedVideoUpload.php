<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class ChunkedVideoUpload extends Field
{
    protected string $view = 'filament.forms.components.chunked-video-upload';

    protected int|string|Closure|null $courseId = null;

    public function courseId(int|string|Closure|null $courseId): static
    {
        $this->courseId = $courseId;

        return $this;
    }

    public function getCourseId(): ?int
    {
        $courseId = $this->evaluate($this->courseId);

        return filled($courseId) ? (int) $courseId : null;
    }

    public function getChunkSize(): int
    {
        return (int) config('chunked_uploads.chunk_size');
    }

    public function getMaxFileSize(): int
    {
        return (int) config('chunked_uploads.max_file_size');
    }
}

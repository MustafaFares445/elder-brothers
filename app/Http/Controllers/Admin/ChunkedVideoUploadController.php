<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseVideo;
use App\Models\User;
use App\Support\ChunkedVideoUploadMetadata;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ChunkedVideoUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin($request);
        $maxFileSize = (int) config('chunked_uploads.max_file_size');

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', "max:{$maxFileSize}"],
            'mime' => ['nullable', 'string', 'max:120'],
        ]);

        abort_unless(strtolower(pathinfo($validated['name'], PATHINFO_EXTENSION)) === 'mp4', 422, __('chunked_upload.invalid_type'));

        $clientMime = strtolower((string) ($validated['mime'] ?? ''));
        $allowedClientMimes = (array) config('chunked_uploads.allowed_client_mime_types', []);

        abort_unless($clientMime === '' || in_array($clientMime, $allowedClientMimes, true), 422, __('chunked_upload.invalid_type'));

        $uploadId = (string) Str::uuid();
        $chunkSize = (int) config('chunked_uploads.chunk_size');
        $totalChunks = (int) ceil($validated['size'] / $chunkSize);
        $directory = $this->uploadDirectory($user, $uploadId);
        $disk = Storage::disk('local');

        $disk->makeDirectory($directory);

        $metadata = [
            'id' => $uploadId,
            'user_id' => $user->getKey(),
            'course_id' => (int) $validated['course_id'],
            'original_name' => $validated['name'],
            'mime' => $clientMime,
            'size' => (int) $validated['size'],
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'next_chunk' => 0,
            'uploaded_bytes' => 0,
            'status' => 'uploading',
            'source_path' => null,
            'sha256' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->writeMetadata($disk, $directory, $metadata);

        return response()->json($this->uploadResponse($metadata), Response::HTTP_CREATED);
    }

    public function show(Request $request, string $upload): JsonResponse
    {
        $user = $this->authorizeAdmin($request);
        $metadata = $this->readOwnedMetadata(Storage::disk('local'), $this->uploadDirectory($user, $upload), $user);

        return response()->json($this->uploadResponse($metadata));
    }

    public function chunk(Request $request, string $upload): JsonResponse
    {
        $user = $this->authorizeAdmin($request);
        $chunkSize = (int) config('chunked_uploads.chunk_size');
        $maxChunkKilobytes = (int) ceil($chunkSize / 1024) + 64;

        $validated = $request->validate([
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', "max:{$maxChunkKilobytes}"],
        ]);

        /** @var UploadedFile $uploadedChunk */
        $uploadedChunk = $validated['chunk'];
        $directory = $this->uploadDirectory($user, $upload);

        $metadata = $this->withUploadLock($directory, function (FilesystemAdapter $disk) use ($directory, $user, $validated, $uploadedChunk): array {
            $metadata = $this->readOwnedMetadata($disk, $directory, $user);

            abort_if($metadata['status'] === 'completed', 409, __('chunked_upload.already_completed'));

            $chunkIndex = (int) $validated['chunk_index'];
            $expectedIndex = (int) $metadata['next_chunk'];
            $partPath = $this->partPath($directory, $chunkIndex);

            if ($chunkIndex < $expectedIndex) {
                abort_unless($disk->exists($partPath), 409, __('chunked_upload.out_of_order'));

                return $metadata;
            }

            abort_unless($chunkIndex === $expectedIndex, 409, __('chunked_upload.out_of_order'));
            abort_unless($chunkIndex < (int) $metadata['total_chunks'], 422, __('chunked_upload.invalid_chunk'));

            $expectedSize = min(
                (int) $metadata['chunk_size'],
                (int) $metadata['size'] - ($chunkIndex * (int) $metadata['chunk_size']),
            );
            $actualSize = (int) $uploadedChunk->getSize();

            abort_unless($actualSize === $expectedSize, 422, __('chunked_upload.invalid_chunk_size'));

            $stream = fopen($uploadedChunk->getRealPath(), 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to open the uploaded video chunk.');
            }

            try {
                $stored = $disk->put($partPath, $stream);
            } finally {
                fclose($stream);
            }

            abort_unless($stored && $disk->exists($partPath), 500, __('chunked_upload.chunk_store_failed'));
            abort_unless((int) $disk->size($partPath) === $expectedSize, 500, __('chunked_upload.chunk_store_failed'));

            $metadata['next_chunk'] = $expectedIndex + 1;
            $metadata['uploaded_bytes'] = min(
                (int) $metadata['size'],
                (int) $metadata['uploaded_bytes'] + $actualSize,
            );
            $metadata['updated_at'] = now()->toIso8601String();

            $this->writeMetadata($disk, $directory, $metadata);

            return $metadata;
        });

        return response()->json($this->uploadResponse($metadata));
    }

    public function complete(Request $request, string $upload): JsonResponse
    {
        $user = $this->authorizeAdmin($request);
        $directory = $this->uploadDirectory($user, $upload);

        $metadata = $this->withUploadLock($directory, function (FilesystemAdapter $disk) use ($directory, $user): array {
            $metadata = $this->readOwnedMetadata($disk, $directory, $user);

            if ($metadata['status'] === 'completed') {
                return $metadata;
            }

            abort_unless(
                (int) $metadata['next_chunk'] === (int) $metadata['total_chunks'],
                409,
                __('chunked_upload.missing_chunks'),
            );

            $metadata['status'] = 'assembling';
            $metadata['updated_at'] = now()->toIso8601String();
            $this->writeMetadata($disk, $directory, $metadata);

            $assembledPath = "{$directory}/assembled.mp4";
            $assembledAbsolutePath = $disk->path($assembledPath);
            $destination = fopen($assembledAbsolutePath, 'wb');

            if (! is_resource($destination)) {
                throw new RuntimeException('Unable to create the assembled video file.');
            }

            $hash = hash_init('sha256');

            try {
                for ($index = 0; $index < (int) $metadata['total_chunks']; $index++) {
                    $partPath = $this->partPath($directory, $index);
                    abort_unless($disk->exists($partPath), 409, __('chunked_upload.missing_chunks'));

                    $source = $disk->readStream($partPath);

                    if (! is_resource($source)) {
                        throw new RuntimeException("Unable to read video chunk {$index}.");
                    }

                    try {
                        while (! feof($source)) {
                            $buffer = fread($source, 1024 * 1024);

                            if ($buffer === false) {
                                throw new RuntimeException("Unable to read video chunk {$index}.");
                            }

                            if ($buffer === '') {
                                continue;
                            }

                            hash_update($hash, $buffer);

                            $offset = 0;
                            $length = strlen($buffer);

                            while ($offset < $length) {
                                $written = fwrite($destination, substr($buffer, $offset));

                                if ($written === false || $written === 0) {
                                    throw new RuntimeException('Unable to assemble the uploaded video.');
                                }

                                $offset += $written;
                            }
                        }
                    } finally {
                        fclose($source);
                    }
                }
            } finally {
                fclose($destination);
            }

            abort_unless((int) filesize($assembledAbsolutePath) === (int) $metadata['size'], 422, __('chunked_upload.size_mismatch'));

            $detectedMime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($assembledAbsolutePath);
            $allowedDetectedMimes = (array) config('chunked_uploads.allowed_detected_mime_types', []);

            abort_unless(in_array(strtolower($detectedMime), $allowedDetectedMimes, true), 422, __('chunked_upload.invalid_type'));

            $mediaDiskName = (string) config('filesystems.course_media', 'local');
            $mediaDisk = Storage::disk($mediaDiskName);
            $sourcePath = sprintf('courses/%d/videos/%s.mp4', $metadata['course_id'], Str::uuid());
            $assembledStream = fopen($assembledAbsolutePath, 'rb');

            if (! is_resource($assembledStream)) {
                throw new RuntimeException('Unable to reopen the assembled video.');
            }

            try {
                $stored = $mediaDisk->put($sourcePath, $assembledStream, ['visibility' => 'private']);
            } finally {
                fclose($assembledStream);
            }

            abort_unless($stored && $mediaDisk->exists($sourcePath), 500, __('chunked_upload.final_store_failed'));

            for ($index = 0; $index < (int) $metadata['total_chunks']; $index++) {
                $disk->delete($this->partPath($directory, $index));
            }

            $disk->delete($assembledPath);

            $metadata['status'] = 'completed';
            $metadata['source_path'] = $sourcePath;
            $metadata['disk'] = $mediaDiskName;
            $metadata['sha256'] = hash_final($hash);
            $metadata['uploaded_bytes'] = (int) $metadata['size'];
            $metadata['updated_at'] = now()->toIso8601String();

            $this->writeMetadata($disk, $directory, $metadata);

            ChunkedVideoUploadMetadata::remember(
                $mediaDiskName,
                $sourcePath,
                (int) $metadata['size'],
                $metadata['sha256'],
            );

            return $metadata;
        });

        return response()->json($this->uploadResponse($metadata));
    }

    public function destroy(Request $request, string $upload): Response
    {
        $user = $this->authorizeAdmin($request);
        $directory = $this->uploadDirectory($user, $upload);
        $disk = Storage::disk('local');

        if (! $disk->exists($this->metadataPath($directory))) {
            return response()->noContent();
        }

        $this->withUploadLock($directory, function (FilesystemAdapter $disk) use ($directory, $user): void {
            $metadata = $this->readOwnedMetadata($disk, $directory, $user);
            $sourcePath = $metadata['source_path'] ?? null;
            $mediaDiskName = (string) ($metadata['disk'] ?? config('filesystems.course_media', 'local'));

            if (filled($sourcePath)) {
                ChunkedVideoUploadMetadata::forget($mediaDiskName, $sourcePath);
            }

            if (filled($sourcePath) && ! CourseVideo::query()
                ->where('source_path', $sourcePath)
                ->where('private_disk', $mediaDiskName)
                ->exists()) {
                Storage::disk($mediaDiskName)->delete($sourcePath);
            }
        });

        $disk->deleteDirectory($directory);

        return response()->noContent();
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->is_admin && $user->status === 'active',
            Response::HTTP_FORBIDDEN,
        );

        return $user;
    }

    private function uploadDirectory(User $user, string $uploadId): string
    {
        abort_unless(Str::isUuid($uploadId), 404);

        return sprintf('chunked-video-uploads/%d/%s', $user->getKey(), $uploadId);
    }

    private function metadataPath(string $directory): string
    {
        return "{$directory}/metadata.json";
    }

    private function partPath(string $directory, int $index): string
    {
        return sprintf('%s/chunk-%08d.part', $directory, $index);
    }

    private function readOwnedMetadata(FilesystemAdapter $disk, string $directory, User $user): array
    {
        $path = $this->metadataPath($directory);

        abort_unless($disk->exists($path), 404);

        $metadata = json_decode((string) $disk->get($path), true, flags: JSON_THROW_ON_ERROR);

        abort_unless((int) ($metadata['user_id'] ?? 0) === (int) $user->getKey(), 404);

        return $metadata;
    }

    private function writeMetadata(FilesystemAdapter $disk, string $directory, array $metadata): void
    {
        $written = $disk->put(
            $this->metadataPath($directory),
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        if (! $written) {
            throw new RuntimeException('Unable to persist chunked upload metadata.');
        }
    }

    private function withUploadLock(string $directory, Closure $callback): mixed
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($this->metadataPath($directory)), 404);

        $lockPath = $disk->path("{$directory}/.lock");
        $lock = fopen($lockPath, 'c+');

        if (! is_resource($lock)) {
            throw new RuntimeException('Unable to lock the video upload session.');
        }

        try {
            abort_unless(flock($lock, LOCK_EX), 503, __('chunked_upload.lock_failed'));

            return $callback($disk);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function uploadResponse(array $metadata): array
    {
        $size = max(1, (int) $metadata['size']);

        return [
            'upload_id' => $metadata['id'],
            'status' => $metadata['status'],
            'chunk_size' => (int) $metadata['chunk_size'],
            'total_chunks' => (int) $metadata['total_chunks'],
            'next_chunk' => (int) $metadata['next_chunk'],
            'uploaded_bytes' => (int) $metadata['uploaded_bytes'],
            'progress' => round(((int) $metadata['uploaded_bytes'] / $size) * 100, 2),
            'source_path' => $metadata['source_path'] ?? null,
            'sha256' => $metadata['sha256'] ?? null,
        ];
    }
}

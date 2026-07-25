<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = '',
        string $code = 'OPERATION_COMPLETED',
        int $status = 200,
        mixed $meta = null
    ): JsonResponse {
        if ($data instanceof LengthAwarePaginator) {
            $meta = [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'has_more' => $data->hasMorePages(),
            ];
            $data = $data->items();
        }

        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $message ?: __('api.operation_completed'),
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    protected function error(string $code, string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'errors' => $errors ?: null,
        ], fn ($value) => $value !== null), $status);
    }
}

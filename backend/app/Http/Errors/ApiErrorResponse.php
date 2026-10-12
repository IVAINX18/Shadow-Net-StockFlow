<?php

declare(strict_types=1);

namespace App\Http\Errors;

use Illuminate\Http\JsonResponse;

// Centralizo la envoltura {"error":{"code","message","details"}} aquí para que
// todos los renders de excepción compartan el mismo formato sin duplicarlo.
final class ApiErrorResponse
{
    public static function make(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}

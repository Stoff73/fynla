<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Trait for handling error responses safely without exposing sensitive information.
 *
 * Use this trait in API controllers to ensure exception messages are logged
 * but not exposed to clients, preventing information leakage.
 */
trait SafeErrorResponse
{
    /**
     * Create a safe error response that logs the full error but returns a sanitized message.
     *
     * @param  string  $context  Human-readable context for the error (e.g., "Failed to create user")
     * @param  \Exception  $e  The exception that was caught
     * @param  int  $statusCode  HTTP status code to return (default: 500)
     */
    protected function safeErrorResponse(string $context, \Exception $e, int $statusCode = 500): JsonResponse
    {
        Log::error("{$context}: {$e->getMessage()}", [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => "{$context}. Please try again or contact support if the problem persists.",
        ], $statusCode);
    }
}

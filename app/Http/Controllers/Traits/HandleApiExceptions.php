<?php

declare(strict_types=1);

namespace App\Http\Controllers\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Trait for standardized API exception handling across controllers.
 *
 * Provides consistent error response format and logging.
 */
trait HandleApiExceptions
{
    /**
     * Handle an exception and return a standardized JSON error response.
     *
     * @param  \Throwable  $e  The exception to handle
     * @param  string  $context  Description of what operation failed
     * @param  int  $statusCode  HTTP status code (default 500)
     */
    protected function handleException(\Throwable $e, string $context, int $statusCode = 500): JsonResponse
    {
        // Log the full error for debugging
        Log::error("{$context}: {$e->getMessage()}", [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => auth()->id(),
        ]);

        // Return sanitized error message to client
        $message = app()->environment('local')
            ? "{$context}: {$e->getMessage()}"
            : $context;

        return response()->json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Handle a validation-style exception (400 Bad Request).
     */
    protected function handleValidationException(\Throwable $e, string $context): JsonResponse
    {
        return $this->handleException($e, $context, 400);
    }

    /**
     * Handle a not found exception (404).
     */
    protected function handleNotFoundException(\Throwable $e, string $context): JsonResponse
    {
        return $this->handleException($e, $context, 404);
    }

    /**
     * Return a success response with data.
     */
    protected function successResponse(mixed $data, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Return an error response without an exception.
     */
    protected function errorResponse(string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }
}

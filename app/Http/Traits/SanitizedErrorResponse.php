<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trait for sanitized error responses
 *
 * This trait provides standardized error handling that:
 * - Logs full error details server-side for debugging
 * - Returns sanitized messages to clients in production
 * - Exposes full details only in debug mode for development
 *
 * SECURITY: Prevents sensitive information leakage through API responses
 */
trait SanitizedErrorResponse
{
    /**
     * Generate a sanitized error response
     *
     * @param  Throwable  $exception  The caught exception
     * @param  string  $context  Human-readable context for the error (e.g., 'Analysis', 'Save operation')
     * @param  int  $statusCode  HTTP status code (default 500)
     * @param  array  $additionalLogContext  Additional context for logging
     */
    protected function errorResponse(
        Throwable $exception,
        string $context = 'Operation',
        int $statusCode = 500,
        array $additionalLogContext = []
    ): JsonResponse {
        // Always log full error details server-side
        Log::error("{$context} failed", array_merge([
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ], $additionalLogContext));

        // Determine the message to return to the client
        $clientMessage = config('app.debug')
            ? "{$context} failed: {$exception->getMessage()}"
            : "{$context} failed. Please try again or contact support if the problem persists.";

        return response()->json([
            'success' => false,
            'message' => $clientMessage,
            // Only include debug info in development
            'debug' => config('app.debug') ? [
                'exception' => get_class($exception),
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
            ] : null,
        ], $statusCode);
    }

    /**
     * Alias for errorResponse() - backward compatibility with SafeErrorResponse trait
     *
     * @param  string  $context  Human-readable context for the error (e.g., "Failed to create user")
     * @param  \Exception  $e  The exception that was caught
     * @param  int  $statusCode  HTTP status code to return (default: 500)
     */
    protected function safeErrorResponse(string $context, \Exception $e, int $statusCode = 500): JsonResponse
    {
        return $this->errorResponse($e, $context, $statusCode);
    }

    /**
     * Generate a not found error response
     *
     * @param  string  $resourceType  The type of resource not found (e.g., 'Account', 'Property')
     */
    protected function notFoundResponse(string $resourceType = 'Resource'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "{$resourceType} not found or access denied.",
        ], 404);
    }

    /**
     * Generate a validation error response
     *
     * @param  string  $message  Validation error message
     * @param  array  $errors  Validation errors array
     */
    protected function validationErrorResponse(string $message, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }
}

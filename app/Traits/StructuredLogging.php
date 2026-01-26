<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Trait for standardized structured logging across the application.
 *
 * Provides consistent logging format with context arrays for better
 * log analysis and debugging.
 */
trait StructuredLogging
{
    /**
     * Log an informational message with structured context.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context data
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($message, $this->buildContext($context));
    }

    /**
     * Log a warning message with structured context.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context data
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, $this->buildContext($context));
    }

    /**
     * Log an error message with structured context.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context data
     * @param  \Throwable|null  $exception  Optional exception to include
     */
    protected function logError(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        $logContext = $this->buildContext($context);

        if ($exception) {
            $logContext['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        Log::error($message, $logContext);
    }

    /**
     * Log a debug message with structured context.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context data
     */
    protected function logDebug(string $message, array $context = []): void
    {
        Log::debug($message, $this->buildContext($context));
    }

    /**
     * Log an authentication event.
     *
     * @param  string  $action  The action being performed (login, logout, etc.)
     * @param  int|null  $userId  The user ID if available
     * @param  array  $context  Additional context data
     */
    protected function logAuth(string $action, ?int $userId = null, array $context = []): void
    {
        $this->logInfo("Auth: {$action}", array_merge([
            'action' => $action,
            'user_id' => $userId,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $context));
    }

    /**
     * Log an API request.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $context  Additional context data
     */
    protected function logApiRequest(string $method, string $endpoint, array $context = []): void
    {
        $this->logInfo("API: {$method} {$endpoint}", array_merge([
            'method' => $method,
            'endpoint' => $endpoint,
        ], $context));
    }

    /**
     * Log a model operation (create, update, delete).
     *
     * @param  string  $operation  The operation (create, update, delete)
     * @param  string  $modelType  The model class name
     * @param  int|string  $modelId  The model ID
     * @param  array  $context  Additional context data
     */
    protected function logModelOperation(string $operation, string $modelType, int|string $modelId, array $context = []): void
    {
        $this->logInfo("Model: {$operation} {$modelType}", array_merge([
            'operation' => $operation,
            'model_type' => $modelType,
            'model_id' => $modelId,
        ], $context));
    }

    /**
     * Log a financial calculation.
     *
     * @param  string  $calculation  The calculation type
     * @param  array  $inputs  Input values
     * @param  mixed  $result  Calculation result
     * @param  array  $context  Additional context data
     */
    protected function logCalculation(string $calculation, array $inputs, mixed $result, array $context = []): void
    {
        $this->logDebug("Calculation: {$calculation}", array_merge([
            'calculation' => $calculation,
            'inputs' => $inputs,
            'result' => $result,
        ], $context));
    }

    /**
     * Build the log context with standard fields.
     *
     * @param  array  $context  Additional context data
     * @return array Complete context array
     */
    private function buildContext(array $context): array
    {
        $standardContext = [
            'class' => static::class,
            'timestamp' => now()->toIso8601String(),
        ];

        // Add user_id if available and not already in context
        if (! isset($context['user_id']) && auth()->check()) {
            $standardContext['user_id'] = auth()->id();
        }

        // Add request_id for tracing if available
        if (request()->hasHeader('X-Request-ID')) {
            $standardContext['request_id'] = request()->header('X-Request-ID');
        }

        return array_merge($standardContext, $context);
    }
}

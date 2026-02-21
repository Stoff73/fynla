<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for financial calculation errors.
 *
 * Provides specific error types for different financial calculation failures,
 * enabling more precise error handling and user-friendly error messages.
 */
class FinancialCalculationException extends RuntimeException
{
    protected string $calculationType;

    protected array $context;

    public function __construct(
        string $message,
        string $calculationType = 'general',
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->calculationType = $calculationType;
        $this->context = $context;
    }

    public function getCalculationType(): string
    {
        return $this->calculationType;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception for division by zero in financial calculations.
     */
    public static function divisionByZero(string $context): self
    {
        return new self(
            "Division by zero in financial calculation: {$context}",
            'division_by_zero',
            ['context' => $context]
        );
    }

    /**
     * Create exception for missing required data.
     */
    public static function missingData(string $dataType, array $context = []): self
    {
        return new self(
            "Missing required data for calculation: {$dataType}",
            'missing_data',
            array_merge(['data_type' => $dataType], $context)
        );
    }

    /**
     * Create exception for invalid input values.
     */
    public static function invalidInput(string $field, mixed $value, string $reason = ''): self
    {
        $message = "Invalid input value for {$field}";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self(
            $message,
            'invalid_input',
            ['field' => $field, 'value' => $value, 'reason' => $reason]
        );
    }

    /**
     * Create exception for tax configuration errors.
     */
    public static function taxConfigError(string $configType, string $reason = ''): self
    {
        $message = "Tax configuration error for {$configType}";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self(
            $message,
            'tax_config_error',
            ['config_type' => $configType, 'reason' => $reason]
        );
    }

    /**
     * Create exception for projection calculation errors.
     */
    public static function projectionError(string $projectionType, string $reason = ''): self
    {
        $message = "Unable to calculate {$projectionType} projection";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self(
            $message,
            'projection_error',
            ['projection_type' => $projectionType, 'reason' => $reason]
        );
    }

    /**
     * Create exception for IHT calculation errors.
     */
    public static function ihtCalculationError(string $reason, array $context = []): self
    {
        return new self(
            "IHT calculation error: {$reason}",
            'iht_calculation',
            $context
        );
    }

    /**
     * Create exception for pension calculation errors.
     */
    public static function pensionCalculationError(string $reason, array $context = []): self
    {
        return new self(
            "Pension calculation error: {$reason}",
            'pension_calculation',
            $context
        );
    }

    /**
     * Create exception for investment calculation errors.
     */
    public static function investmentCalculationError(string $reason, array $context = []): self
    {
        return new self(
            "Investment calculation error: {$reason}",
            'investment_calculation',
            $context
        );
    }

    /**
     * Create exception for protection adequacy calculation errors.
     */
    public static function protectionCalculationError(string $reason, array $context = []): self
    {
        return new self(
            "Protection calculation error: {$reason}",
            'protection_calculation',
            $context
        );
    }

    /**
     * Create exception for insufficient data to perform calculation.
     */
    public static function insufficientData(string $calculationType, array $missingFields = []): self
    {
        $message = "Insufficient data to perform {$calculationType} calculation";
        if (! empty($missingFields)) {
            $message .= '. Missing: '.implode(', ', $missingFields);
        }

        return new self(
            $message,
            'insufficient_data',
            ['calculation_type' => $calculationType, 'missing_fields' => $missingFields]
        );
    }

    /**
     * Create exception for calculation timeout.
     */
    public static function timeout(string $calculationType, int $timeoutSeconds): self
    {
        return new self(
            "{$calculationType} calculation timed out after {$timeoutSeconds} seconds",
            'timeout',
            ['calculation_type' => $calculationType, 'timeout_seconds' => $timeoutSeconds]
        );
    }
}

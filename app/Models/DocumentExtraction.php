<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'extraction_version',
        'model_used',
        'input_tokens',
        'output_tokens',
        'raw_response',
        'extracted_fields',
        'field_confidence',
        'warnings',
        'target_model',
        'target_model_id',
        'is_valid',
        'validation_errors',
    ];

    protected $casts = [
        'extracted_fields' => 'array',
        'field_confidence' => 'array',
        'warnings' => 'array',
        'validation_errors' => 'array',
        'is_valid' => 'boolean',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'extraction_version' => 'integer',
    ];

    /**
     * Hide the raw response from API output (large payload).
     */
    protected $hidden = [
        'raw_response',
    ];

    /**
     * Get the document that this extraction belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get total tokens used for this extraction.
     */
    public function getTotalTokensAttribute(): int
    {
        return ($this->input_tokens ?? 0) + ($this->output_tokens ?? 0);
    }

    /**
     * Get the average confidence across all fields.
     */
    public function getAverageConfidenceAttribute(): float
    {
        $confidences = $this->field_confidence ?? [];

        if (empty($confidences)) {
            return 0.0;
        }

        return array_sum($confidences) / count($confidences);
    }

    /**
     * Get fields with low confidence (below threshold).
     */
    public function getLowConfidenceFields(float $threshold = 0.7): array
    {
        $lowConfidence = [];
        $confidences = $this->field_confidence ?? [];

        foreach ($confidences as $field => $confidence) {
            if ($confidence < $threshold) {
                $lowConfidence[$field] = $confidence;
            }
        }

        return $lowConfidence;
    }

    /**
     * Get fields with high confidence (above threshold).
     */
    public function getHighConfidenceFields(float $threshold = 0.9): array
    {
        $highConfidence = [];
        $confidences = $this->field_confidence ?? [];

        foreach ($confidences as $field => $confidence) {
            if ($confidence >= $threshold) {
                $highConfidence[$field] = $confidence;
            }
        }

        return $highConfidence;
    }

    /**
     * Check if all required fields were extracted.
     */
    public function hasAllRequiredFields(array $requiredFields): bool
    {
        $extracted = $this->extracted_fields ?? [];

        foreach ($requiredFields as $field) {
            if (! isset($extracted[$field]) || $extracted[$field] === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get extracted value for a specific field.
     */
    public function getFieldValue(string $field, mixed $default = null): mixed
    {
        return $this->extracted_fields[$field] ?? $default;
    }

    /**
     * Get confidence for a specific field.
     */
    public function getFieldConfidence(string $field): float
    {
        return $this->field_confidence[$field] ?? 0.0;
    }

    /**
     * Get confidence level label for a field.
     */
    public function getFieldConfidenceLevel(string $field): string
    {
        $confidence = $this->getFieldConfidence($field);

        return match (true) {
            $confidence >= 0.95 => 'very_high',
            $confidence >= 0.80 => 'high',
            $confidence >= 0.60 => 'medium',
            $confidence >= 0.40 => 'low',
            default => 'very_low',
        };
    }

    /**
     * Check if extraction has warnings.
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Check if extraction has validation errors.
     */
    public function hasValidationErrors(): bool
    {
        return ! empty($this->validation_errors);
    }

    /**
     * Get the target model instance if confirmed.
     */
    public function getTargetModelInstance(): ?Model
    {
        if (! $this->target_model || ! $this->target_model_id) {
            return null;
        }

        if (! class_exists($this->target_model)) {
            return null;
        }

        return $this->target_model::find($this->target_model_id);
    }

    /**
     * Get the short model name for display.
     */
    public function getTargetModelNameAttribute(): ?string
    {
        if (! $this->target_model) {
            return null;
        }

        return class_basename($this->target_model);
    }
}

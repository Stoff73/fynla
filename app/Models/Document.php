<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'original_filename',
        'stored_filename',
        'disk',
        'path',
        'mime_type',
        'file_size',
        'document_type',
        'detected_document_subtype',
        'detection_confidence',
        'status',
        'error_message',
        'processed_at',
        'confirmed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'detection_confidence' => 'decimal:4',
        'processed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    /**
     * Document type constants
     */
    public const TYPE_PENSION_STATEMENT = 'pension_statement';

    public const TYPE_INSURANCE_POLICY = 'insurance_policy';

    public const TYPE_INVESTMENT_STATEMENT = 'investment_statement';

    public const TYPE_MORTGAGE_STATEMENT = 'mortgage_statement';

    public const TYPE_SAVINGS_STATEMENT = 'savings_statement';

    public const TYPE_PROPERTY_DOCUMENT = 'property_document';

    public const TYPE_LPA = 'lpa_document';

    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Status constants
     */
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_REVIEW_PENDING = 'review_pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Get the user that owns the document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all extractions for the document.
     */
    public function extractions(): HasMany
    {
        return $this->hasMany(DocumentExtraction::class);
    }

    /**
     * Get the latest extraction for the document.
     */
    public function latestExtraction(): HasOne
    {
        return $this->hasOne(DocumentExtraction::class)->latest();
    }

    /**
     * Get all logs for the document.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(DocumentExtractionLog::class);
    }

    /**
     * Get the full URL to the document file.
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->disk === 'public') {
            return Storage::disk($this->disk)->url($this->path);
        }

        return null;
    }

    /**
     * Get the file contents.
     */
    public function getContents(): string
    {
        return Storage::disk($this->disk)->get($this->path);
    }

    /**
     * Get the file contents as base64.
     */
    public function getBase64Contents(): string
    {
        return base64_encode($this->getContents());
    }

    /**
     * Check if the document is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the document is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Check if the document is currently being processed.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the document extraction failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the document is ready for review.
     */
    public function isReadyForReview(): bool
    {
        return in_array($this->status, [self::STATUS_EXTRACTED, self::STATUS_REVIEW_PENDING]);
    }

    /**
     * Check if the document has been confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by document type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }
}

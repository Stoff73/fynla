<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExtractionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Action constants
     */
    public const ACTION_UPLOADED = 'uploaded';

    public const ACTION_EXTRACTION_STARTED = 'extraction_started';

    public const ACTION_EXTRACTION_COMPLETED = 'extraction_completed';

    public const ACTION_EXTRACTION_FAILED = 'extraction_failed';

    public const ACTION_FIELDS_MODIFIED = 'fields_modified';

    public const ACTION_CONFIRMED = 'confirmed';

    public const ACTION_SAVED_TO_MODEL = 'saved_to_model';

    public const ACTION_DELETED = 'deleted';

    /**
     * Get the document that this log belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a log entry for a document.
     */
    public static function log(
        Document $document,
        User $user,
        string $action,
        array $metadata = []
    ): self {
        return self::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'action' => $action,
            'metadata' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get human-readable action name.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_UPLOADED => 'Document uploaded',
            self::ACTION_EXTRACTION_STARTED => 'Extraction started',
            self::ACTION_EXTRACTION_COMPLETED => 'Extraction completed',
            self::ACTION_EXTRACTION_FAILED => 'Extraction failed',
            self::ACTION_FIELDS_MODIFIED => 'Fields modified',
            self::ACTION_CONFIRMED => 'Data confirmed',
            self::ACTION_SAVED_TO_MODEL => 'Saved to database',
            self::ACTION_DELETED => 'Document deleted',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Scope to filter by document.
     */
    public function scopeForDocument(Builder $query, int $documentId): Builder
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by action.
     */
    public function scopeWithAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }
}

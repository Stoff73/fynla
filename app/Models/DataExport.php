<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataExport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const FORMAT_JSON = 'json';

    public const FORMAT_CSV = 'csv';

    // Export files expire after 7 days
    public const EXPIRY_DAYS = 7;

    protected $fillable = [
        'user_id',
        'status',
        'format',
        'file_path',
        'file_size',
        'requested_at',
        'completed_at',
        'expires_at',
        'downloaded_at',
        'ip_address',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new export request
     */
    public static function createRequest(int $userId, string $format = self::FORMAT_JSON): self
    {
        return self::create([
            'user_id' => $userId,
            'status' => self::STATUS_PENDING,
            'format' => $format,
            'requested_at' => now(),
            'ip_address' => request()->ip(),
        ]);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markCompleted(string $filePath, int $fileSize): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'completed_at' => now(),
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
        ]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    public function markExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    public function markDownloaded(): void
    {
        $this->update(['downloaded_at' => now()]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->isCompleted() && ! $this->isExpired() && $this->file_path;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now())
            ->where('status', '!=', self::STATUS_EXPIRED);
    }
}

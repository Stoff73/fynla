<?php

declare(strict_types=1);

namespace App\Models\Insights;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class InsightArticleRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    /** A person saved this in the CMS. */
    public const SOURCE_CMS = 'cms';

    /** An automated import from the Google Drive Articles folder wrote this. */
    public const SOURCE_DRIVE_IMPORT = 'drive-import';

    /**
     * How many versions the admin revert panel offers. Older rows are kept —
     * the table is an append-only audit trail — they are simply not offered.
     */
    public const HISTORY_LIMIT = 5;

    protected $fillable = [
        'article_id',
        'title',
        'subtitle',
        'summary',
        'body_blocks',
        'saved_by',
        'source',
        'saved_at',
    ];

    protected $casts = [
        'body_blocks' => 'array',
        'saved_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(InsightArticle::class, 'article_id');
    }

    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('InsightArticleRevision is append-only; updates are not permitted.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('InsightArticleRevision is append-only; deletions are not permitted.');
        });
    }
}

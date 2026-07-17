<?php

declare(strict_types=1);

namespace App\Models\Insights;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsightArticle extends Model
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'summary',
        'category',
        'tags',
        'hero_image_path',
        'hero_image_card_path',
        'hero_image_thumb_path',
        'body_blocks',
        'template_id',
        'status',
        'is_featured',
        'is_bespoke',
        'bespoke_component',
        'published_at',
        'scheduled_at',
        'author_id',
        'authors',
        'meta_title',
        'meta_description',
        'canonical_url',
        'pipeline_campaign_id',
        'source_docx_drive_file_id',
        'source_docx_drive_url',
        'source_docx_hash',
        'source_docx_imported_at',
        'dev_synced_at',
        'prod_synced_at',
        'prod_pushed_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'body_blocks' => 'array',
        'authors' => 'array',
        'is_featured' => 'boolean',
        'is_bespoke' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'source_docx_imported_at' => 'datetime',
        'dev_synced_at' => 'datetime',
        'prod_synced_at' => 'datetime',
    ];

    public const ALLOWED_AUTHORS = [
        'Brett Isenberg',
        'Azlan Raj',
        'Chris Slater-Jones',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InsightTemplate::class, 'template_id');
    }

    public function pipelineCampaign(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Pipeline\PipelineCampaign::class, 'pipeline_campaign_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(\App\Models\Pipeline\ArticleSyncLog::class)
            ->orderByDesc('created_at');
    }

    public function revisions(): HasMany
    {
        // Secondary sort by id — when two revisions are written in the same
        // millisecond (common in fast tests), saved_at alone would tie and
        // make ->first() non-deterministic.
        return $this->hasMany(InsightArticleRevision::class, 'article_id')
            ->orderByDesc('saved_at')
            ->orderByDesc('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBespoke(Builder $query, bool $bespoke = true): Builder
    {
        return $query->where('is_bespoke', $bespoke);
    }

    public function scopeScheduledDue(Builder $query): Builder
    {
        return $query->where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }
}

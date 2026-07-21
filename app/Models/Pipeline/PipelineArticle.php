<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineArticle extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'platform_post_ids' => 'array',
        'metrics_24h' => 'array',
        'metrics_48h' => 'array',
        'boost' => 'array',
        'boost_eligible' => 'boolean',
        'retry_count' => 'integer',
        'script_cost_gbp' => 'float',
        'script_generated_at' => 'datetime',
        'clip_paths' => 'array',
        'captions_burned' => 'boolean',
        'clips_generated_at' => 'datetime',
        'source_video_duration_s' => 'integer',
        'video_cost_gbp' => 'float',
        'published_at' => 'datetime',
    ];

    public function insightArticle(): BelongsTo
    {
        return $this->belongsTo(InsightArticle::class);
    }

    public function documentArticle(): BelongsTo
    {
        return $this->belongsTo(DocumentArticle::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PipelineRun::class);
    }

    /**
     * The pipeline article is sourced from either a native InsightArticle or a
     * DocumentArticle (CMS). These helpers resolve the common fields the
     * pipeline needs regardless of which CMS the article came from.
     */
    public function hasSource(): bool
    {
        return $this->insightArticle !== null || $this->documentArticle !== null;
    }

    public function sourceSlug(): ?string
    {
        return $this->insightArticle?->slug ?? $this->documentArticle?->slug;
    }

    public function sourceTitle(): ?string
    {
        return $this->insightArticle?->title ?? $this->documentArticle?->title;
    }
}

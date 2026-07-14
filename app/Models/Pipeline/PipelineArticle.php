<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

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
        'published_at' => 'datetime',
    ];

    public function insightArticle(): BelongsTo
    {
        return $this->belongsTo(InsightArticle::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PipelineRun::class);
    }
}

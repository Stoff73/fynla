<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelinePost extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'hashtags' => 'array',
        'metrics_24h' => 'array',
        'metrics_7d' => 'array',
        'metrics_30d' => 'array',
        'clip_index' => 'integer',
        'retry_count' => 'integer',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function pipelineArticle(): BelongsTo
    {
        return $this->belongsTo(PipelineArticle::class);
    }

    public function pipelineCampaign(): BelongsTo
    {
        return $this->belongsTo(PipelineCampaign::class);
    }

    public function scopeAwaitingApproval(Builder $q): Builder
    {
        return $q->where('status', 'awaiting_approval');
    }

    public function scopeApprovedUnscheduled(Builder $q): Builder
    {
        return $q->where('status', 'approved')->whereNull('scheduled_at');
    }
}

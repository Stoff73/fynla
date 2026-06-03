<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $guarded = [];

    protected $casts = [
        'lastmod' => 'date',
        'published_at' => 'datetime',
        'topic_confidence' => 'float',
        'claude_response' => 'array',
        'platform_post_ids' => 'array',
        'metrics_24h' => 'array',
        'metrics_48h' => 'array',
        'boost' => 'array',
        'boost_eligible' => 'boolean',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(PipelineAsset::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function pipelineRuns(): HasMany
    {
        return $this->hasMany(PipelineRun::class);
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->save();

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function pipelineArticle(): BelongsTo
    {
        return $this->belongsTo(PipelineArticle::class);
    }
}

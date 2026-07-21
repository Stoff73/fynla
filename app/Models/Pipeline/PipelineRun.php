<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineRun extends Model
{
    // Renamed from `pipeline_runs` to avoid a collision with dev's own
    // `pipeline_runs` table (App\Models\PipelineRun) discovered when the
    // marketing branch merged 1,727 commits of dev. Dev's table keeps the
    // canonical name; the marketing content pipeline uses this prefixed one.
    protected $table = 'marketing_pipeline_runs';

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

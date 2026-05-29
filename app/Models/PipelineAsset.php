<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineAsset extends Model
{
    // Content-marketing pipeline asset. Renamed from App\Models\Asset to remove the
    // basename collision with the core estate App\Models\Estate\Asset (table `assets`).
    // Table stays `pipeline_assets`; pin it explicitly so Eloquent's basename
    // convention does not resolve it onto the estate table.
    protected $table = 'pipeline_assets';

    protected $guarded = [];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}

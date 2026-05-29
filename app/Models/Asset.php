<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    // Distinct from the core estate App\Models\Estate\Asset (table `assets`).
    // This is the content-marketing pipeline asset; pin its table explicitly so
    // Eloquent's basename convention does not resolve it onto the estate table.
    protected $table = 'pipeline_assets';

    protected $guarded = [];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}

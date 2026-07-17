<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Estate\Liability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiabilityValueSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'liability_id', 'column_name', 'value', 'currency', 'value_gbp',
        'taken_at', 'trigger_reason', 'ingest_source',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'value_gbp' => 'decimal:2',
        'taken_at' => 'datetime',
    ];

    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }
}

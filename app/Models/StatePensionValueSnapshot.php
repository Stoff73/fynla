<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatePensionValueSnapshot extends Model
{
    use HasFactory;

    protected $table = 'state_pension_value_snapshots';

    protected $fillable = [
        'state_pension_id', 'column_name', 'value', 'currency', 'value_gbp',
        'taken_at', 'trigger_reason', 'ingest_source',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'value_gbp' => 'decimal:2',
        'taken_at' => 'datetime',
    ];

    public function statePension(): BelongsTo
    {
        return $this->belongsTo(StatePension::class);
    }
}

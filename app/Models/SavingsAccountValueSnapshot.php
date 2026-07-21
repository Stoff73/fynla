<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsAccountValueSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_account_id', 'column_name', 'value', 'currency', 'value_gbp',
        'taken_at', 'trigger_reason', 'ingest_source',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'value_gbp' => 'decimal:2',
        'taken_at' => 'datetime',
    ];

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
    }
}

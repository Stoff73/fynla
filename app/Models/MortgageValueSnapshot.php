<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MortgageValueSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['mortgage_id', 'snapshot_type', 'value', 'snapshotted_at'];

    protected $casts = [
        'value' => 'decimal:4',
        'snapshotted_at' => 'datetime',
    ];

    public function mortgage(): BelongsTo
    {
        return $this->belongsTo(Mortgage::class);
    }
}

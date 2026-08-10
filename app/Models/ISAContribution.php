<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ISAContribution extends Model
{
    use HasFactory;

    protected $table = 'isa_contributions';

    protected $fillable = [
        'user_id',
        'account_type',
        'account_id',
        'tax_year',
        'contribution_date',
        'entry_type',
        'amount',
        'source',
        'provenance',
    ];

    protected $casts = [
        'contribution_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'account_type', 'account_id');
    }
}

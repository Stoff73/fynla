<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NetWorthForecastAssumption extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'property',
        'investments',
        'pensions',
        'cash',
        'business',
        'valuables',
        'mortgages',
        'other_liabilities',
        'basis',
        'effective_from',
    ];

    protected $casts = [
        'property' => 'decimal:3',
        'investments' => 'decimal:3',
        'pensions' => 'decimal:3',
        'cash' => 'decimal:3',
        'business' => 'decimal:3',
        'valuables' => 'decimal:3',
        'mortgages' => 'decimal:3',
        'other_liabilities' => 'decimal:3',
        'effective_from' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

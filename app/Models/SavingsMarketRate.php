<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsMarketRate extends Model
{
    protected $fillable = [
        'rate_key',
        'label',
        'rate',
        'tax_year',
        'effective_from',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'effective_from' => 'date',
    ];
}

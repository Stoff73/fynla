<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = ['from_ccy', 'to_ccy', 'rate', 'effective_at', 'source'];

    protected $casts = [
        'rate' => 'decimal:8',
        'effective_at' => 'datetime',
    ];
}

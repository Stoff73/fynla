<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ISAAllowanceTracking extends Model
{
    use Auditable, HasFactory;

    protected $table = 'isa_allowance_tracking';

    protected $fillable = [
        'user_id',
        'tax_year',
        'cash_isa_used',
        'stocks_shares_isa_used',
        'lisa_used',
        'total_used',
        'total_allowance',
    ];

    protected $casts = [
        'cash_isa_used' => 'decimal:2',
        'stocks_shares_isa_used' => 'decimal:2',
        'lisa_used' => 'decimal:2',
        'total_used' => 'decimal:2',
        'total_allowance' => 'decimal:2',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

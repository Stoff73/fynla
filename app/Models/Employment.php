<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One job. A user may hold several; users.annual_employment_income and
 * users.annual_self_employment_income are the maintained totals of these rows
 * (see the create_employments_table migration for why both exist).
 */
class Employment extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employer',
        'occupation',
        'annual_income',
        'income_type',
    ];

    protected $casts = [
        'annual_income' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

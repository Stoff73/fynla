<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenditureProfile extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'monthly_housing',
        'monthly_utilities',
        'monthly_food',
        'monthly_transport',
        'monthly_insurance',
        'monthly_loans',
        'monthly_discretionary',
        'total_monthly_expenditure',
    ];

    protected $casts = [
        'monthly_housing' => 'float',
        'monthly_utilities' => 'float',
        'monthly_food' => 'float',
        'monthly_transport' => 'float',
        'monthly_insurance' => 'float',
        'monthly_loans' => 'float',
        'monthly_discretionary' => 'float',
        'total_monthly_expenditure' => 'float',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

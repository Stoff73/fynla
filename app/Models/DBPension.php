<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DB Pension Model
 *
 * Represents a Defined Benefit pension scheme (final salary, career average, or public sector).
 *
 * IMPORTANT: DB pensions are captured for projection only - no DB to DC transfer advice is provided.
 */
class DBPension extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'db_pensions';

    protected $fillable = [
        'user_id',
        'scheme_name',
        'scheme_type',
        'accrued_annual_pension',
        'pensionable_service_years',
        'pensionable_salary',
        'normal_retirement_age',
        'revaluation_method',
        'spouse_pension_percent',
        'lump_sum_entitlement',
        'inflation_protection',
    ];

    protected $casts = [
        'accrued_annual_pension' => 'decimal:2',
        'pensionable_service_years' => 'decimal:2',
        'pensionable_salary' => 'decimal:2',
        'normal_retirement_age' => 'integer',
        'spouse_pension_percent' => 'decimal:2',
        'lump_sum_entitlement' => 'decimal:2',
    ];

    /**
     * Get the user that owns the DB pension.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

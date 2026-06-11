<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AwardsDataEntryPoints;
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
    use Auditable, AwardsDataEntryPoints, HasFactory, SoftDeletes;

    public function gamificationCategory(): string
    {
        return 'pension';
    }

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
        // SP1 Pass 3 / PR 6 — derived columns
        'projected_annual_pension_at_nra_gbp',
        'projected_annual_pension_at_nra_gbp_calculated_at',
        'spouse_pension_projected_gbp',
        'spouse_pension_projected_gbp_calculated_at',
    ];

    protected $casts = [
        'accrued_annual_pension' => 'decimal:2',
        'pensionable_service_years' => 'decimal:2',
        'pensionable_salary' => 'decimal:2',
        'normal_retirement_age' => 'integer',
        'spouse_pension_percent' => 'decimal:2',
        'lump_sum_entitlement' => 'decimal:2',
        // SP1 Pass 3 / PR 6 — derived columns
        'projected_annual_pension_at_nra_gbp' => 'decimal:2',
        'projected_annual_pension_at_nra_gbp_calculated_at' => 'datetime',
        'spouse_pension_projected_gbp' => 'decimal:2',
        'spouse_pension_projected_gbp_calculated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the DB pension.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

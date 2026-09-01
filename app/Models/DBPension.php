<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\PensionEnums;
use App\Models\Concerns\AwardsDataEntryPoints;
use App\Services\Retirement\RetirementAgeResolver;
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
 *
 * UNIT CONVENTION — `spouse_pension_percent` is stored in PERCENTAGE POINTS.
 * 50% is stored as 50.00, never 0.50. Every consumer divides by 100
 * (HouseholdPlanningService, PensionDerivedColumnCalculator) and both writers
 * validate `min:0|max:100` (StoreDBPensionRequest, PensionStore). The document
 * importer once stored the fraction instead, which put every spouse projection
 * for an imported pension at a hundredth of the real figure — see W-0030 and
 * migration 2026_08_21_120000, which also records this on the column itself.
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
        'scheme_status',
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

    /**
     * Default scheme retirement age when the pension does not record one.
     *
     * Deliberately the same as the household default: if the two disagreed, a pension
     * could count as income from one age while being projected forward from another
     * (W-0036). W-0196 turned that agreement from a comment into a reference — the
     * value cannot now be changed in one place and not the other.
     */
    public const DEFAULT_NORMAL_RETIREMENT_AGE = RetirementAgeResolver::DEFAULT_RETIREMENT_AGE;

    /**
     * Is this pension actually paying out yet?
     *
     * The only question that matters for income, and nothing was asking it.
     * `accrued_annual_pension` holds a FUTURE figure — the form labels the input
     * "Annual Income at Retirement" — so treating any non-zero value as income
     * today told a 48-year-old with a Normal Retirement Age of 60 that she was
     * receiving £35,000 a year (W-0036). That flowed into income tax, the Personal
     * Allowance taper, Child Benefit and her retirement target.
     *
     * `scheme_status` answers this directly and is preferred whenever the user has
     * stated it (W-0032 gave it a column; the vocabulary is `PensionEnums`, which
     * lives in App\Constants so that validators can read it without breaching the
     * LOCKED pension store boundary). It is decisive in both directions: a
     * scheme "In Payment" is paying regardless of age, and one "Active" or
     * "Deferred" is not paying even past the scheme's retirement age. That matters
     * because age alone is wrong in both directions in cases common in Fynla's
     * audience — drawing early at 57 against a scheme age of 60, or deferring at 62
     * past a scheme age of 60.
     *
     * Age against the scheme's retirement age remains the fallback for every row
     * that predates the column, where `scheme_status` is null. Those rows are
     * deliberately not backfilled: their status is unknown, and guessing it would
     * invent the fact the column exists to record.
     *
     * A null age with no stated status means we cannot establish that it is in
     * payment, so it is not counted. Inventing income is the failure being fixed
     * here.
     */
    public function isInPayment(?int $userAge = null): bool
    {
        if ($this->scheme_status !== null) {
            return $this->scheme_status === PensionEnums::SCHEME_STATUS_IN_PAYMENT;
        }

        $age = $userAge ?? $this->user?->date_of_birth?->age;

        if ($age === null) {
            return false;
        }

        return $age >= ($this->normal_retirement_age ?? self::DEFAULT_NORMAL_RETIREMENT_AGE);
    }
}

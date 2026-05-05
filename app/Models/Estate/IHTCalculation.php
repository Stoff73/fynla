<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IHTCalculation extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'iht_calculations';

    protected $fillable = [
        'user_id',
        'user_gross_assets',
        'spouse_gross_assets',
        'total_gross_assets',
        'user_total_liabilities',
        'spouse_total_liabilities',
        'total_liabilities',
        'user_net_estate',
        'spouse_net_estate',
        'total_net_estate',
        'nrb_available',
        'nrb_message',
        'rnrb_available',
        'rnrb_status',
        'rnrb_message',
        'total_allowances',
        'taxable_estate',
        'iht_liability',
        'effective_rate',
        'projected_gross_assets',
        'projected_liabilities',
        'projected_net_estate',
        'projected_taxable_estate',
        'projected_iht_liability',
        'projected_cash',
        'projected_investments',
        'projected_properties',
        'retirement_age',
        'result_json',
        'years_to_death',
        'estimated_age_at_death',
        'calculation_date',
        'is_married',
        'data_sharing_enabled',
        'assets_hash',
        'liabilities_hash',
    ];

    protected $casts = [
        'user_gross_assets' => 'decimal:2',
        'spouse_gross_assets' => 'decimal:2',
        'total_gross_assets' => 'decimal:2',
        'user_total_liabilities' => 'decimal:2',
        'spouse_total_liabilities' => 'decimal:2',
        'total_liabilities' => 'decimal:2',
        'user_net_estate' => 'decimal:2',
        'spouse_net_estate' => 'decimal:2',
        'total_net_estate' => 'decimal:2',
        'nrb_available' => 'decimal:2',
        'rnrb_available' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'taxable_estate' => 'decimal:2',
        'iht_liability' => 'decimal:2',
        'effective_rate' => 'decimal:2',
        'projected_gross_assets' => 'decimal:2',
        'projected_liabilities' => 'decimal:2',
        'projected_net_estate' => 'decimal:2',
        'projected_taxable_estate' => 'decimal:2',
        'projected_iht_liability' => 'decimal:2',
        'projected_cash' => 'decimal:2',
        'projected_investments' => 'decimal:2',
        'projected_properties' => 'decimal:2',
        'retirement_age' => 'integer',
        'result_json' => 'array',
        'years_to_death' => 'integer',
        'estimated_age_at_death' => 'integer',
        'calculation_date' => 'datetime',
        'is_married' => 'boolean',
        'data_sharing_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

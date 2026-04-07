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
        'user_gross_assets' => 'float',
        'spouse_gross_assets' => 'float',
        'total_gross_assets' => 'float',
        'user_total_liabilities' => 'float',
        'spouse_total_liabilities' => 'float',
        'total_liabilities' => 'float',
        'user_net_estate' => 'float',
        'spouse_net_estate' => 'float',
        'total_net_estate' => 'float',
        'nrb_available' => 'float',
        'rnrb_available' => 'float',
        'total_allowances' => 'float',
        'taxable_estate' => 'float',
        'iht_liability' => 'float',
        'effective_rate' => 'float',
        'projected_gross_assets' => 'float',
        'projected_liabilities' => 'float',
        'projected_net_estate' => 'float',
        'projected_taxable_estate' => 'float',
        'projected_iht_liability' => 'float',
        'projected_cash' => 'float',
        'projected_investments' => 'float',
        'projected_properties' => 'float',
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

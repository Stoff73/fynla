<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bequest extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'will_id',
        'will_document_id',
        'user_id',
        'beneficiary_name',
        'beneficiary_user_id',
        'beneficiary_type',
        'charity_registration_number',
        'bequest_type',
        'percentage_of_estate',
        'specific_amount',
        'specific_asset_description',
        'asset_id',
        'priority_order',
        'conditions',
        'notes',
    ];

    protected $casts = [
        'percentage_of_estate' => 'decimal:2',
        'specific_amount' => 'decimal:2',
        'priority_order' => 'integer',
    ];

    /**
     * Get the will that this bequest belongs to
     */
    public function will(): BelongsTo
    {
        return $this->belongsTo(Will::class);
    }

    /**
     * Get the user that created this bequest
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the beneficiary user if applicable
     */
    public function beneficiaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    /**
     * Check if this bequest is to a charity
     *
     * A bequest is considered charitable if:
     * - beneficiary_type is 'charity'
     * - Has a charity registration number
     * - Beneficiary name contains charity indicators
     *
     * The ONE home for this decision (Rule 20). WillAnalysisService carried a
     * near-identical private copy until 2026-08-21, and the two had already
     * drifted: that copy also treated 'trust' as a charity indicator, so a
     * "Smith Family Trust" counted toward the charitable total and could push a
     * user onto the reduced Inheritance Tax rate they do not qualify for. A
     * gift into a family trust is a chargeable transfer, not an exempt one —
     * 'trust' is a structure word, not a charity word, and must not come back.
     *
     * Known limitation: the two structured checks above are the reliable ones,
     * but no write path in the application populates beneficiary_type or
     * charity_registration_number today, so in practice every determination
     * falls through to the name list.
     */
    public function isCharitable(): bool
    {
        // Check beneficiary_type
        if ($this->beneficiary_type === 'charity') {
            return true;
        }

        // Check charity registration number
        if (! empty($this->charity_registration_number)) {
            return true;
        }

        // Check beneficiary name for charity indicators
        $name = strtolower($this->beneficiary_name ?? '');
        $charityIndicators = [
            'charity',
            'charitable',
            'foundation',
            'cancer',
            'heart',
            'hospice',
            'nspcc',
            'rspca',
            'oxfam',
            'red cross',
            'british heart',
            'macmillan',
            'marie curie',
            'shelter',
            'save the children',
            'unicef',
        ];

        foreach ($charityIndicators as $indicator) {
            if (str_contains($name, $indicator)) {
                return true;
            }
        }

        return false;
    }
}

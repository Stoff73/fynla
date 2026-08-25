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
     * The limitation this docblock used to record — "no write path populates
     * beneficiary_type" — was true and is now fixed. Every write path classifies
     * the beneficiary through nameLooksCharitable() below and stores the result,
     * so the structured check is the one that answers first and the name list is
     * the fallback it was always meant to be (W-0394).
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

        return self::nameLooksCharitable($this->beneficiary_name ?? '');
    }

    /**
     * Does this free-text beneficiary name read as a charity?
     *
     * The ONE home for the name list. It was inline in isCharitable() and had
     * no other caller, so every write path stored the schema default
     * `individual` and a gift to Cancer Research UK was recorded as a gift to a
     * person. Nothing broke visibly, because isCharitable() re-derived the
     * answer from the name on every read — but the stored data contradicted
     * what the application believed, and any charity the list does not name
     * (Guide Dogs, a local hospice trust, an air ambulance) had no second
     * chance: it was an individual in the database and an individual to the
     * charitable total, which is what decides the reduced Inheritance Tax rate.
     *
     * 'trust' is deliberately absent and must stay absent — a gift into a
     * family trust is a chargeable transfer, not an exempt one. See the note in
     * isCharitable() above.
     */
    public static function nameLooksCharitable(string $name): bool
    {
        $name = strtolower($name);

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

    /**
     * The beneficiary type to store when the caller has not stated one.
     *
     * A user who explicitly says "this is a charity" is always believed; this
     * only fills the silence, and it fills it with the same judgement
     * isCharitable() would reach anyway, so the stored row and the derived
     * answer cannot disagree (Rule 20).
     */
    public static function inferBeneficiaryType(string $name): string
    {
        return self::nameLooksCharitable($name) ? 'charity' : 'individual';
    }
}

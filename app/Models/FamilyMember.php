<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class FamilyMember extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $hidden = [
        'national_insurance_number',
    ];

    protected $fillable = [
        'user_id',
        'household_id',
        'relationship',
        'stated_relationship',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        // SECURITY: national_insurance_number intentionally excluded from $fillable.
        // Set explicitly in FamilyMembersController to prevent mass assignment of PII.
        'annual_income',
        'is_dependent',
        'education_status',
        'receives_child_benefit',
        'linked_user_id',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'annual_income' => 'decimal:2',
        'is_dependent' => 'boolean',
        'receives_child_benefit' => 'boolean',
    ];

    /**
     * Append the computed age so it always appears in toArray / toJson output.
     * Without this, API consumers and tests that expect `family_member.age`
     * see nothing even when date_of_birth is populated correctly (B-3).
     *
     * `is_linked_account` rides along for the same reason: it is THE answer to
     * "is this row backed by a real account" (see isLinkedAccount below), and
     * appending it on the model is the only way every surface — web, /m, iOS,
     * every endpoint that serialises a family member — reads one answer instead
     * of re-deriving it (Rule 20). `display_relationship` rides along on the same
     * argument — what to CALL this person is one answer, not one per surface.
     *
     * @var list<string>
     */
    protected $appends = ['age', 'is_linked_account', 'display_relationship', 'resolved_annual_income'];

    /**
     * Keep the legacy `name` column in step with the name parts.
     *
     * `name` predates first_name/middle_name/last_name and is NOT NULL DEFAULT
     * 'Unknown', so any writer that set the parts and not `name` stored the
     * literal string "Unknown" — which is what the web Family Details heading,
     * the estate plan's children list and the savings child-name copy all read.
     * Eight places create these rows; four of them left `name` unset (both
     * spouse-linking paths, both Fyn onboarding paths) and five more repeated
     * this exact derivation by hand.
     *
     * Filling it here is the one place a new writer cannot miss. It only ever
     * replaces the column default (or an empty string) — a name a caller set
     * deliberately is left alone, including the whole names OnboardingService
     * writes with no first_name and the display names the update endpoint
     * accepts. So this fills the gap without taking anything over.
     */
    private const LEGACY_NAME_DEFAULT = 'Unknown';

    protected static function booted(): void
    {
        static::saving(function (self $member): void {
            $current = trim((string) ($member->name ?? ''));
            if ($current !== '' && $current !== self::LEGACY_NAME_DEFAULT) {
                return;
            }

            $derived = trim(implode(' ', array_filter([
                $member->first_name,
                $member->middle_name,
                $member->last_name,
            ])));

            if ($derived !== '') {
                $member->name = $derived;
            }
        });
    }

    /**
     * THE translation from the relationships the product offers to the values
     * the column can actually hold (Rule 20).
     *
     * `family_members.relationship` is
     * `enum('spouse','child','parent','other_dependent')`. The family form
     * offers six options, two of which the column has never had — and the
     * connection runs in strict mode, so `partner` and `step_child` did not
     * degrade, they raised `SQLSTATE[01000] 1265 Data truncated` and the request
     * 500ed with the raw SQL in the message. That form is the same component on
     * `/settings/family` and on onboarding step 2, so a step-parent adding their
     * step-child hit a database error on their first run through the product
     * (W-0114).
     *
     * The mapping itself is not new: `CoordinatingAgent::handleCreateFamilyMember`
     * had it inline, so Fyn could add a step-child while the form could not. It
     * lives here now because this model owns the column, which makes it the one
     * place a new writer cannot miss.
     *
     * @var array<string, array{relationship: string, note: string}>
     */
    public const RELATIONSHIP_ALIASES = [
        'step_child' => ['relationship' => 'child', 'note' => 'Step child'],
        'partner' => ['relationship' => 'other_dependent', 'note' => 'Partner (unmarried)'],
    ];

    /**
     * Resolve a requested relationship into the three things a caller needs: the
     * value the column can hold, the relationship the user actually chose, and
     * the note that gives a human reading the record the same context.
     *
     * `stated` is NULL when nothing was translated, so it means "as stated
     * equals as stored" and every pre-existing row is already correct without a
     * backfill. It is display only — nothing branches on it, which is what keeps
     * this additive rather than semantic.
     *
     * @return array{relationship: string, stated: ?string, note: ?string}
     */
    public static function resolveRelationship(string $requested): array
    {
        $alias = self::RELATIONSHIP_ALIASES[$requested] ?? null;

        return [
            'relationship' => $alias['relationship'] ?? $requested,
            'stated' => $alias === null ? null : $requested,
            'note' => $alias['note'] ?? null,
        ];
    }

    /**
     * User-facing wording where the column name and correct British English
     * differ (W-0115).
     *
     * "Dependent" is the adjective; the noun is "**dependant**", and CLAUDE.md
     * requires British spelling in user-facing text. The column keeps the
     * American form because it is code. Only values that differ appear here —
     * everything else is its own name with the underscores taken out.
     *
     * Found by consolidating: of the four relationship formatters that existed,
     * the ONE in the Savings modal had this right and the family cards had it
     * wrong. Consolidating on what most call sites did would have propagated the
     * error into the only place that was correct.
     *
     * @var array<string, string>
     */
    private const RELATIONSHIP_WORDS = [
        'other_dependent' => 'other dependant',
    ];

    /**
     * THE relationship to show a user (Rule 20).
     *
     * What they chose where it was translated, what is stored where it was not.
     * Never the raw enum on an aliased row: that is how the application ends up
     * telling somebody their partner is a dependent (W-0114). Underscores become
     * spaces; the surfaces apply their own capitalisation.
     *
     * Computed here rather than on each client so web, `/m` and native inherit
     * one answer AND one spelling without a second edit.
     */
    public function getDisplayRelationshipAttribute(): string
    {
        $value = (string) ($this->stated_relationship ?: $this->relationship);

        return self::RELATIONSHIP_WORDS[$value] ?? str_replace('_', ' ', $value);
    }

    /**
     * Fold the alias note into whatever notes the caller supplied, mapping note
     * first so the context reads before the detail. Returns null when there is
     * nothing to record, so callers can leave the column alone.
     */
    public static function composeRelationshipNotes(?string $aliasNote, ?string $suppliedNotes): ?string
    {
        $supplied = trim((string) $suppliedNotes);

        if ($aliasNote === null) {
            return $supplied !== '' ? $supplied : null;
        }

        return trim($aliasNote.($supplied !== '' ? '. '.$supplied : ''));
    }

    /**
     * Get the user that owns this family member record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the household this family member belongs to.
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the linked user account (for spouse records that map to a real user).
     */
    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    /**
     * THE single rule for "is this family member backed by a linked Fynla
     * account" (Rule 20).
     *
     * `relationship` answers a question about the household — this person is my
     * spouse. It has never answered the question the interface actually asks
     * before it withholds Edit and Delete and prints "Linked account", which is
     * whether a real account sits behind the row. Those are different facts, and
     * every writer that cannot establish a link still writes the relationship.
     * W-0051: onboarding produced a spouse row with `linked_user_id` NULL, the
     * card claimed a link, the controls were removed on the strength of the
     * claim, and the record became unreachable from every surface.
     *
     * Liveness is part of the rule, not a refinement of it. `linked_user_id`
     * deliberately survives the linked account being deleted — everything is
     * retained for regulatory purposes (August/August19Updates/spec/
     * deleted-spouse-visibility.md §1) — but from that moment there is no
     * account to log into, so telling the user to manage the record there would
     * strand them exactly as the NULL case did.
     */
    public function isLinkedAccount(): bool
    {
        return $this->liveLinkedUser() !== null;
    }

    /**
     * Appended so the predicate crosses the API boundary once and every client
     * reads the same boolean. Web, /m and iOS must never re-derive this.
     */
    public function getIsLinkedAccountAttribute(): bool
    {
        return $this->isLinkedAccount();
    }

    /**
     * The income of a linked account is the ACCOUNT's income, not the copy that
     * was written onto the row when the member was created (W-0176).
     *
     * `family_members.annual_income` is a snapshot. For an unlinked member it is
     * the only figure there is. For a linked spouse it goes stale the moment they
     * update their own profile, and the card then prints "Annual Income £0"
     * beside an account earning £120,000 — the `decimal:2` cast makes the stale
     * zero the string "0.00", which is truthy in the template, so the row renders
     * rather than hiding.
     *
     * Appended rather than overriding `annual_income` itself: that attribute has
     * a `decimal:2` cast and is writable through `UpdateRecordAllowlist`, and an
     * accessor of the same name collides with both. Resolved here rather than in
     * the client so web, /m and iOS cannot disagree.
     */
    public function getResolvedAnnualIncomeAttribute(): ?string
    {
        $linked = $this->liveLinkedUser();

        if ($linked === null || $linked->annual_employment_income === null) {
            return $this->annual_income;
        }

        return number_format((float) $linked->annual_employment_income, 2, '.', '');
    }

    /**
     * The linked account while it is live, or null.
     *
     * Resolved WITHOUT lazy loading — `Model::preventLazyLoading()` is on, so
     * reaching for `$this->linkedUser` on a model loaded as part of a collection
     * throws, and this attribute is appended to every serialisation. Queries
     * explicitly and caches, the same shape as `User::liveSpouse()`. Rows with a
     * NULL `linked_user_id` — every non-spouse member — never touch the database.
     */
    public function liveLinkedUser(): ?User
    {
        if ($this->linked_user_id === null) {
            return null;
        }

        if ($this->relationLoaded('linkedUser')) {
            return $this->getRelation('linkedUser');
        }

        if (! $this->liveLinkedUserResolved) {
            $this->liveLinkedUserCache = $this->linkedUser()->first();
            $this->liveLinkedUserResolved = true;
        }

        return $this->liveLinkedUserCache;
    }

    /**
     * Cached outside the relation registry deliberately: setRelation() would
     * flip relationLoaded('linkedUser') to true and start eager-serialising the
     * whole linked user into every family-member payload.
     */
    private ?User $liveLinkedUserCache = null;

    private bool $liveLinkedUserResolved = false;

    /**
     * B-3 — computed age from date_of_birth. Returns null when DOB is
     * missing. Uses diffInYears (not floor(diffInMonths/12)) so leap
     * years and month boundaries work correctly.
     */
    public function getAgeAttribute(): ?int
    {
        if ($this->date_of_birth === null) {
            return null;
        }

        return (int) $this->date_of_birth->diffInYears(now());
    }

    /**
     * Accessor: Get the full name from name parts (for backward compatibility)
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Encrypted national insurance number accessor
     */
    protected function nationalInsuranceNumber(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (! $value) {
                    return null;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException $e) {
                    return $value;
                }
            },
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}

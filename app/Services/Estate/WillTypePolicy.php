<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\User;

/**
 * The single home for "which will structures will Fynla build, for whom".
 *
 * CSJ direction 2026-08-21 (W-0019): a married user — or a civil partner — is
 * offered mirror wills ONLY. Any other structure they ask for, on any surface,
 * is met with a clear refusal and a solicitor referral. CSJ answered the open
 * question the same day: a married user whose partner will not make a matching
 * will gets the solicitor message too. There is no one-sided will inside a
 * marriage, and no "proceed anyway".
 *
 * Rule 20 — this class is the ONE place the decision and its wording live. The
 * web will builder reads it through `will-builder/pre-populate`, the API
 * refusals return it, and Fyn reaches it through the per-turn context layer in
 * FynContextAssembler. A second copy of this copy anywhere is a violation.
 *
 * Copy reviewed 2026-08-21 by compliance-lead (advice-vs-guidance boundary; it
 * blocked an earlier draft that asserted an Inheritance Tax consequence we
 * cannot source) and design-lead (voice, and the civil-partnership wording).
 */
final class WillTypePolicy
{
    public const SIMPLE = 'simple';

    public const MIRROR = 'mirror';

    /**
     * Declared statuses that mean "married" for will-structure purposes.
     */
    public const MARRIED_STATUSES = ['married', 'civil_partnership'];

    public const REFUSAL_HEADING = 'Mirror Wills Only';

    /**
     * Shown to a married user in place of the simple-will option, returned in
     * the API refusal, and quoted verbatim by Fyn.
     *
     * Stored as paragraphs because the web step renders the first paragraph as
     * body text and the rest inside the notice block, while the API and Fyn
     * need the whole message.
     */
    public const REFUSAL_MARRIED = [
        'A mirror will is the only will we can build for you here — a matching pair, one for you and one for your spouse or civil partner, each leaving to the other first, then to the beneficiaries you both choose.',
        "We can't build a will for one of you on its own. A will for one spouse or civil partner alone is outside what this tool is designed to do.",
        "If you want a different arrangement, please speak to a qualified solicitor. This tool doesn't provide legal advice — a solicitor can take your full circumstances into account and draft a will to match.",
    ];

    /**
     * Shown to a married user whose partner is not going to make a matching
     * will. The closing clause is a Consumer Duty mitigation, not a flourish:
     * without it the likely misreading is "I cannot have a will at all", and
     * the user does nothing.
     */
    public const REFUSAL_NO_MIRROR_PARTNER = [
        "We can't build your will here. A mirror will only works as a pair — we build both from the same details, and each of you signs and witnesses your own. If your spouse or civil partner isn't going to make theirs, there's nothing to mirror.",
        "That's a limit of this tool, not a comment on your situation.",
        "Please speak to a qualified solicitor. This tool doesn't provide legal advice — a solicitor can advise on what fits your circumstances, including where only one of you is making a will.",
    ];

    /**
     * Is this user married, for will-structure purposes?
     *
     * A declared status is authoritative in BOTH directions. `spouse_id`
     * deliberately survives a divorce and survives the partner deleting their
     * account (see User::spouse), so it must never override a user who has told
     * us they are single, divorced or widowed. It is only consulted when the
     * user has told us nothing.
     */
    public function isMarried(User $user): bool
    {
        $declared = (string) ($user->marital_status ?? '');

        if (in_array($declared, self::MARRIED_STATUSES, true)) {
            return true;
        }

        if ($declared !== '') {
            return false;
        }

        return $user->liveSpouseId() !== null;
    }

    /**
     * A mirror will needs a live linked partner account to mirror into.
     */
    public function canBuildMirror(User $user): bool
    {
        return $this->isMarried($user) && $user->liveSpouseId() !== null;
    }

    /**
     * The will types this user may build. Unmarried users are unaffected by
     * W-0019 and keep the simple will (W-0019 acceptance 3).
     *
     * @return list<string>
     */
    public function allowedWillTypes(User $user): array
    {
        if ($this->isMarried($user)) {
            return $this->canBuildMirror($user) ? [self::MIRROR] : [];
        }

        return $user->liveSpouseId() !== null
            ? [self::SIMPLE, self::MIRROR]
            : [self::SIMPLE];
    }

    /**
     * The refusal paragraphs for this user, or null when nothing is refused.
     *
     * Pass the type the user asked for to gate a request. Pass null to ask
     * "what must this user be told about the structures we will not build" —
     * which is what the will builder needs before they have chosen anything,
     * and what Fyn needs before the user has phrased a request. A married user
     * with a partner is answered with REFUSAL_MARRIED for a null request (it
     * explains why there is no choice) but NOT for a mirror request, which is
     * the one structure they may have.
     *
     * @return list<string>|null
     */
    public function refusalFor(User $user, ?string $requestedType = null): ?array
    {
        if (! $this->isMarried($user)) {
            return null;
        }

        if (! $this->canBuildMirror($user)) {
            return self::REFUSAL_NO_MIRROR_PARTNER;
        }

        if ($requestedType === self::MIRROR) {
            return null;
        }

        return self::REFUSAL_MARRIED;
    }

    /**
     * The payload every client renders from — web today, and any future /m or
     * native will surface without a second decision being written.
     *
     * @return array{
     *     married: bool,
     *     allowed_will_types: list<string>,
     *     mirror_available: bool,
     *     can_build: bool,
     *     refusal_heading: string,
     *     refusal: list<string>|null
     * }
     */
    public function payloadFor(User $user): array
    {
        $allowed = $this->allowedWillTypes($user);

        return [
            'married' => $this->isMarried($user),
            'allowed_will_types' => $allowed,
            'mirror_available' => in_array(self::MIRROR, $allowed, true),
            'can_build' => $allowed !== [],
            'refusal_heading' => self::REFUSAL_HEADING,
            'refusal' => $this->refusalFor($user),
        ];
    }

    /**
     * Flatten a refusal for surfaces that take one string (API `message`,
     * exception text, Fyn).
     *
     * @param  list<string>  $paragraphs
     */
    public static function asText(array $paragraphs): string
    {
        return implode("\n\n", $paragraphs);
    }
}

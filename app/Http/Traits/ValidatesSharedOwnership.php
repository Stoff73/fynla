<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Support\SharedOwnership;
use Illuminate\Contracts\Validation\Validator;

/**
 * Refuses a stated ownership share that is not a shared split (W-0040).
 *
 * Lives here rather than under App\Http\Requests because everything in that
 * namespace must extend FormRequest and be suffixed `Request` — an expectation
 * whose whole point is to stop non-request classes accumulating in a request
 * namespace. A shared trait is exactly what it excludes, and App\Http\Traits is
 * where this codebase already keeps HTTP-layer traits.
 *
 * The one place the refusal is written. Every asset form request that accepts
 * `ownership_percentage` calls this, so the rule cannot come to mean different
 * things on savings and on property — which is how the app arrived at refusing
 * a stated `0` while silently rewriting a stated `100` to `50`.
 *
 * Says nothing when the caller stated no share: that case is a default, not an
 * assertion, and `SharedOwnership` resolves it downstream. A form with no share
 * input therefore never sees this rule — which is every form today.
 */
trait ValidatesSharedOwnership
{
    protected function validateSharedOwnershipSplit(
        Validator $validator,
        ?string $ownershipType,
        mixed $statedShare
    ): void {
        if (! SharedOwnership::isShared($ownershipType)) {
            return;
        }

        if (SharedOwnership::statedShare($statedShare) === null) {
            return;
        }

        if (SharedOwnership::isValidSharedSplit($statedShare)) {
            return;
        }

        $validator->errors()->add(
            'ownership_percentage',
            'A shared asset is split between two owners, so your share must be above 0% and below 100%. If you own all of it, choose individual ownership.'
        );
    }

    /**
     * A shared asset must name the person it is shared with.
     *
     * **W-0142.** This lived inline in the two chattel requests and nowhere else,
     * so a shared PROPERTY or MORTGAGE saved with no counterparty at all — the
     * record then asserts that half of it belongs to somebody, without saying who,
     * and every share calculation downstream has a co-owner it cannot name.
     *
     * Lifted into the trait rather than copied a third and fourth time. All four
     * requests already use it, and a rule duplicated per asset type is how
     * chattels ended up with a guard the others never got.
     *
     * `namesCounterparty()` accepts a linked account OR a typed name, which is
     * why the message offers both: a co-owner who is not a Fynla user is still a
     * co-owner, and refusing them would be a worse defect than the one this fixes.
     *
     * **Create-time only, deliberately.** An UPDATE must not have to re-state a
     * counterparty the record already carries: demanding it would turn every
     * unrelated edit — a lender rename, a balance correction — into a 422 for a
     * field the user never touched, which is a worse defect than the one this
     * closes. `SharedOwnership::applyTo()` preserves the stored counterparty, and
     * `UpdateChattelRequest` separately guards the case that actually orphans a
     * record: an explicit `joint_owner_id: null`.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function validateSharedOwnershipCounterparty(
        Validator $validator,
        ?string $ownershipType,
        array $payload
    ): void {
        if (! SharedOwnership::isShared($ownershipType)) {
            return;
        }

        if (SharedOwnership::namesCounterparty($payload)) {
            return;
        }

        $validator->errors()->add(
            'joint_owner_id',
            'Choose who this is owned with, or enter their name.',
        );
    }
}

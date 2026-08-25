<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Estate\WillTypePolicy;

/**
 * W-0019 — married users get mirror wills only, everywhere.
 */
beforeEach(function () {
    $this->policy = app(WillTypePolicy::class);
});

function linkedSpouses(string $status = 'married'): array
{
    $a = User::factory()->create(['marital_status' => $status]);
    $b = User::factory()->create(['marital_status' => $status]);
    $a->update(['spouse_id' => $b->id]);
    $b->update(['spouse_id' => $a->id]);

    return [$a->fresh(), $b->fresh()];
}

describe('WillTypePolicy marital determination', function () {
    it('treats a declared married status as married', function () {
        [$user] = linkedSpouses();

        expect($this->policy->isMarried($user))->toBeTrue();
    });

    it('treats a civil partnership as married for will purposes', function () {
        [$user] = linkedSpouses('civil_partnership');

        expect($this->policy->isMarried($user))->toBeTrue();
    });

    it('lets a declared divorced status beat a lingering spouse_id', function () {
        // spouse_id deliberately survives a divorce (User::spouse), so it must
        // never override what the user has told us.
        [$user] = linkedSpouses();
        $user->update(['marital_status' => 'divorced']);

        expect($this->policy->isMarried($user->fresh()))->toBeFalse();
        expect($this->policy->allowedWillTypes($user->fresh()))->toContain(WillTypePolicy::SIMPLE);
    });

    it('falls back to a live linked partner when no status is declared', function () {
        [$user] = linkedSpouses();
        $user->update(['marital_status' => null]);

        expect($this->policy->isMarried($user->fresh()))->toBeTrue();
    });

    it('does not treat a lone user as married', function () {
        $user = User::factory()->create(['marital_status' => 'single', 'spouse_id' => null]);

        expect($this->policy->isMarried($user))->toBeFalse();
    });
});

describe('WillTypePolicy allowed will types', function () {
    it('offers a married user the mirror will only', function () {
        [$user] = linkedSpouses();

        expect($this->policy->allowedWillTypes($user))->toBe([WillTypePolicy::MIRROR]);
    });

    it('offers an unmarried user the simple will, untouched by W-0019', function () {
        $user = User::factory()->create(['marital_status' => 'single', 'spouse_id' => null]);

        expect($this->policy->allowedWillTypes($user))->toBe([WillTypePolicy::SIMPLE]);
        expect($this->policy->refusalFor($user, WillTypePolicy::SIMPLE))->toBeNull();
    });

    it('offers a married user with no live partner account nothing at all', function () {
        // CSJ 2026-08-21: a married user whose partner will not engage gets the
        // solicitor message too — no one-sided will.
        $user = User::factory()->create(['marital_status' => 'married', 'spouse_id' => null]);

        expect($this->policy->allowedWillTypes($user))->toBe([]);
        expect($this->policy->canBuildMirror($user))->toBeFalse();
        expect($this->policy->refusalFor($user))->toBe(WillTypePolicy::REFUSAL_NO_MIRROR_PARTNER);
    });
});

describe('WillTypePolicy refusals', function () {
    it('refuses a simple will for a married user', function () {
        [$user] = linkedSpouses();

        expect($this->policy->refusalFor($user, WillTypePolicy::SIMPLE))
            ->toBe(WillTypePolicy::REFUSAL_MARRIED);
    });

    it('allows the mirror will it does offer', function () {
        [$user] = linkedSpouses();

        expect($this->policy->refusalFor($user, WillTypePolicy::MIRROR))->toBeNull();
    });

    it('explains why there is no choice before the user has picked one', function () {
        [$user] = linkedSpouses();

        expect($this->policy->refusalFor($user))->toBe(WillTypePolicy::REFUSAL_MARRIED);
    });

    it('names a solicitor in every refusal, and never abbreviates a tax', function () {
        $all = array_merge(WillTypePolicy::REFUSAL_MARRIED, WillTypePolicy::REFUSAL_NO_MIRROR_PARTNER);
        $text = WillTypePolicy::asText($all);

        expect($text)->toContain('solicitor');
        // Rule 9 — no acronyms in user-facing text.
        expect($text)->not->toMatch('/\b(IHT|RNRB|NRB|LPA|CGT)\b/');
    });
});

describe('WillTypePolicy payload', function () {
    it('gives every client the same answer to render', function () {
        [$user] = linkedSpouses();

        $payload = $this->policy->payloadFor($user);

        expect($payload['married'])->toBeTrue();
        expect($payload['allowed_will_types'])->toBe([WillTypePolicy::MIRROR]);
        expect($payload['mirror_available'])->toBeTrue();
        expect($payload['can_build'])->toBeTrue();
        expect($payload['refusal'])->toBe(WillTypePolicy::REFUSAL_MARRIED);
        expect($payload['refusal_heading'])->toBe(WillTypePolicy::REFUSAL_HEADING);
    });

    it('tells a married user with no partner account they cannot build here', function () {
        $user = User::factory()->create(['marital_status' => 'married', 'spouse_id' => null]);

        $payload = $this->policy->payloadFor($user);

        expect($payload['can_build'])->toBeFalse();
        expect($payload['refusal'])->toBe(WillTypePolicy::REFUSAL_NO_MIRROR_PARTNER);
    });
});

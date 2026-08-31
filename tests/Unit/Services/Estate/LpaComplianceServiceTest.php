<?php

declare(strict_types=1);

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaAttorney;
use App\Models\Estate\LpaNotificationPerson;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\LpaCheckPolicy;
use App\Services\Estate\LpaComplianceService;

beforeEach(function () {
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $this->service = new LpaComplianceService;
    $this->user = User::factory()->create();
});

describe('checkCompliance', function () {
    it('returns structured compliance result', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        $result = $this->service->checkCompliance($lpa);

        expect($result)->toHaveKeys([
            'checks', 'passed', 'failed', 'warnings',
            'outcome', 'outcome_label', 'heading',
            'not_checked_heading', 'not_checked_intro', 'not_checked',
            'not_checked_close', 'referral',
        ])
            ->and($result['checks'])->toBeArray()
            ->and($result)->not->toHaveKey('overall_status');
    });

    it('fails when no attorneys appointed', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        $result = $this->service->checkCompliance($lpa);

        $attorneyCheck = collect($result['checks'])->firstWhere('key', 'attorney_count');
        expect($attorneyCheck['status'])->toBe('fail');
    });

    it('passes when attorney is appointed', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id]);

        $result = $this->service->checkCompliance($lpa);

        $attorneyCheck = collect($result['checks'])->firstWhere('key', 'attorney_count');
        expect($attorneyCheck['status'])->toBe('pass');
    });

    it('fails donor age check when under 18', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'donor_date_of_birth' => now()->subYears(16),
            ]);

        $result = $this->service->checkCompliance($lpa);

        $ageCheck = collect($result['checks'])->firstWhere('key', 'donor_age');
        expect($ageCheck['status'])->toBe('fail');
    });

    it('passes donor age check when 18 or older', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'donor_date_of_birth' => now()->subYears(55),
            ]);

        $result = $this->service->checkCompliance($lpa);

        $ageCheck = collect($result['checks'])->firstWhere('key', 'donor_age');
        expect($ageCheck['status'])->toBe('pass');
    });

    it('requires decision type when multiple primary attorneys', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'attorney_decision_type' => null,
            ]);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id, 'attorney_type' => 'primary']);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id, 'attorney_type' => 'primary']);

        $result = $this->service->checkCompliance($lpa);

        $decisionCheck = collect($result['checks'])->firstWhere('key', 'decision_type');
        expect($decisionCheck['status'])->toBe('fail');
    });

    it('fails certificate provider 2-year rule', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'certificate_provider_name' => 'Dr Smith',
                'certificate_provider_known_years' => 1,
            ]);

        $result = $this->service->checkCompliance($lpa);

        $yearsCheck = collect($result['checks'])->firstWhere('key', 'certificate_provider_years');
        expect($yearsCheck['status'])->toBe('fail');
    });

    it('fails when more than 5 notification persons', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaNotificationPerson::factory(6)->create(['lasting_power_of_attorney_id' => $lpa->id]);

        $result = $this->service->checkCompliance($lpa);

        $notifyCheck = collect($result['checks'])->firstWhere('key', 'notification_limit');
        expect($notifyCheck['status'])->toBe('fail');
    });

    it('warns when no replacement attorneys', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
        ]);

        $result = $this->service->checkCompliance($lpa);

        $replacementCheck = collect($result['checks'])->firstWhere('key', 'replacement_attorneys');
        expect($replacementCheck['status'])->toBe('warning');
    });

    it('checks when_can_act for property financial type', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->create([
                'user_id' => $this->user->id,
                'lpa_type' => 'property_financial',
                'when_attorneys_can_act' => null,
            ]);

        $result = $this->service->checkCompliance($lpa);

        $whenCheck = collect($result['checks'])->firstWhere('key', 'when_can_act');
        expect($whenCheck)->not->toBeNull()
            ->and($whenCheck['status'])->toBe('fail');
    });

    it('checks life sustaining treatment for health welfare type', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->create([
                'user_id' => $this->user->id,
                'lpa_type' => 'health_welfare',
                'life_sustaining_treatment' => null,
            ]);

        $result = $this->service->checkCompliance($lpa);

        $lifeCheck = collect($result['checks'])->firstWhere('key', 'life_sustaining');
        expect($lifeCheck)->not->toBeNull()
            ->and($lifeCheck['status'])->toBe('fail');
    });

    it('does not check when_can_act for health welfare type', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->healthWelfare()
            ->create(['user_id' => $this->user->id]);

        $result = $this->service->checkCompliance($lpa);

        $whenCheck = collect($result['checks'])->firstWhere('key', 'when_can_act');
        expect($whenCheck)->toBeNull();
    });

    it('reports no issues found when every check passes, and never a verdict', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->registered()
            ->create([
                'user_id' => $this->user->id,
                'donor_date_of_birth' => now()->subYears(55),
                'certificate_provider_name' => 'Dr Smith',
                'certificate_provider_known_years' => 5,
                'when_attorneys_can_act' => 'only_when_lost_capacity',
            ]);
        // W-0105 — a COMPLETE property and financial affairs instrument now
        // answers the bankruptcy question. Leaving it null is a real warning
        // (s13(8) disqualifies a bankrupt attorney), so a fixture describing a
        // finished LPA has to state it rather than inherit silence.
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'is_bankrupt' => false,
        ]);
        LpaAttorney::factory()->replacement()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'is_bankrupt' => false,
        ]);
        LpaNotificationPerson::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
        ]);

        $result = $this->service->checkCompliance($lpa);

        // This is the state that produced the green "Compliant" badge from
        // 1a3d17e99 (2026-03-16) until W-0100. It now describes the act.
        expect($result['failed'])->toBe(0)
            ->and($result['warnings'])->toBe(0)
            ->and($result['outcome'])->toBe(LpaCheckPolicy::OUTCOME_NO_ISSUES)
            ->and($result['outcome_label'])->toBe('No issues found in these checks');
    });

    it('names what it did not check alongside every result', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        $result = $this->service->checkCompliance($lpa);

        // A bare count would only say the list changed size. These two entries are
        // the ones that must never be dropped: the first is what keeps every party-role
        // check honest about what a name comparison can do (W-0102), and the second is
        // the disqualification limb compliance ruled disclosure-only because "family
        // member" is undefined in the regulations (W-0151).
        expect($result['not_checked'])->toBe(LpaCheckPolicy::NOT_CHECKED)
            ->and($result['not_checked'])->not->toBeEmpty()
            ->and($result['not_checked'])->toContain('Whether two people whose names you typed differently are the same person, or two people with the same name are different people. We compare only the names you entered.')
            ->and($result['not_checked'])->toContain('Whether anything disqualifies your certificate provider from giving the certificate — including being a member of your family.')
            ->and($result['referral'])->toContain('qualified solicitor')
            ->and($result['referral'])->not->toContain('Financial Conduct Authority');
    });

    it('uses singular wording when exactly one check trips', function () {
        expect(LpaCheckPolicy::outcomeLabel(1, 0))->toBe('One check did not pass')
            ->and(LpaCheckPolicy::outcomeLabel(2, 0))->toBe('Some checks did not pass')
            ->and(LpaCheckPolicy::outcomeLabel(0, 1))->toBe('One check raised a point to look at')
            ->and(LpaCheckPolicy::outcomeLabel(0, 2))->toBe('Some checks raised a point to look at');
    });

    // Rule 9 — no acronyms in user-facing text. Every string this service hands
    // a client is user-facing.
    it('spells out Lasting Power of Attorney and Office of the Public Guardian', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        $result = $this->service->checkCompliance($lpa);

        $strings = array_merge(
            [$result['outcome_label'], $result['heading'], $result['not_checked_heading'],
                $result['not_checked_intro'], $result['not_checked_close'], $result['referral']],
            $result['not_checked'],
            array_column($result['checks'], 'title'),
            array_column($result['checks'], 'description'),
        );

        foreach ($strings as $string) {
            expect($string)->not->toMatch('/\bLPA\b/')
                ->and($string)->not->toMatch('/\bOPG\b/');
        }
    });
});

/**
 * W-0102 + W-0103 + W-0151 — the party-role check, folded into one mechanism.
 *
 * The name comparison routes to `WillDocumentService::isSameParty()`, which was
 * already the one home for that question (W-0024). These tests pin the routing as
 * much as the checks: if someone writes a second comparator, the case, whitespace
 * and "Dave Jones" tests below describe behaviour they will have to reproduce.
 */
describe('party role conflicts', function () {
    $partyCheck = function (array $result, string $key): ?array {
        return collect($result['checks'])->firstWhere('key', $key);
    };

    it('fails when the certificate provider is also an attorney, and cites both instruments', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'donor_full_name' => 'Patricia Bennett',
            'certificate_provider_name' => 'Harold Bennett',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Harold Bennett',
        ]);

        $check = $partyCheck($this->service->checkCompliance($lpa), 'party_roles_certificate_provider_attorney');

        expect($check)->not->toBeNull()
            ->and($check['status'])->toBe('fail')
            ->and($check['title'])->toBe('Your certificate provider is also named as an attorney')
            ->and($check['description'])->toContain('Schedule 1, paragraph 2(6)')
            ->and($check['description'])->toContain('regulation 8(3)(b)');
    });

    // Team-lead: match W-0024's gate, which the tester verified BOTH ways — fires
    // when the conflict exists, clears when it is corrected.
    it('clears once the conflict is corrected', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'certificate_provider_name' => 'Harold Bennett',
        ]);
        $attorney = LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Harold Bennett',
        ]);

        expect($partyCheck($this->service->checkCompliance($lpa), 'party_roles_certificate_provider_attorney'))
            ->not->toBeNull();

        $attorney->update(['full_name' => 'Nadia Bennett']);

        $corrected = $this->service->checkCompliance($lpa->fresh());
        expect($partyCheck($corrected, 'party_roles_certificate_provider_attorney'))->toBeNull()
            ->and($partyCheck($corrected, 'party_roles')['status'])->toBe('pass');
    });

    it('ignores case and surrounding whitespace, because isSameParty does', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'certificate_provider_name' => '  harold   BENNETT ',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Harold Bennett',
        ]);

        expect($partyCheck($this->service->checkCompliance($lpa), 'party_roles_certificate_provider_attorney'))
            ->not->toBeNull();
    });

    // The limit this check has, proved rather than asserted. It is disclosed once in
    // LpaCheckPolicy::NOT_CHECKED — this test is what keeps that disclosure honest.
    it('does not catch a differently spelled name, which is why the limit is disclosed', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'certificate_provider_name' => 'Dave Jones',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'David Jones',
        ]);

        expect($partyCheck($this->service->checkCompliance($lpa), 'party_roles_certificate_provider_attorney'))
            ->toBeNull()
            ->and(LpaCheckPolicy::NOT_CHECKED)
            ->toContain('Whether two people whose names you typed differently are the same person, or two people with the same name are different people. We compare only the names you entered.');
    });

    it('fails when the certificate provider is an attorney on the donor other instrument', function () use ($partyCheck) {
        $health = LastingPowerOfAttorney::factory()->healthWelfare()->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $health->id,
            'attorney_type' => 'primary',
            'full_name' => 'Harold Bennett',
        ]);

        $property = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'certificate_provider_name' => 'Harold Bennett',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $property->id,
            'attorney_type' => 'primary',
            'full_name' => 'Nadia Bennett',
        ]);

        $check = $partyCheck($this->service->checkCompliance($property), 'party_roles_certificate_provider_other_instrument');

        expect($check)->not->toBeNull()
            ->and($check['status'])->toBe('fail')
            ->and($check['description'])->toContain('regulation 8(3)(c)');
    });

    it('does not reach across to another user instrument', function () use ($partyCheck) {
        $stranger = User::factory()->create();
        $theirs = LastingPowerOfAttorney::factory()->healthWelfare()->create(['user_id' => $stranger->id]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $theirs->id,
            'attorney_type' => 'primary',
            'full_name' => 'Harold Bennett',
        ]);

        $mine = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'certificate_provider_name' => 'Harold Bennett',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $mine->id,
            'attorney_type' => 'primary',
            'full_name' => 'Nadia Bennett',
        ]);

        expect($partyCheck($this->service->checkCompliance($mine), 'party_roles_certificate_provider_other_instrument'))
            ->toBeNull();
    });

    // Compliance searched for an express prohibition on a donor naming themselves and
    // did not find one, so these are warnings describing a contradiction — never
    // failures asserting a rule.
    it('warns, and does not fail, when the donor is named as their own attorney', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'donor_full_name' => 'Patricia Bennett',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Patricia Bennett',
        ]);

        $check = $partyCheck($this->service->checkCompliance($lpa), 'party_roles_donor_attorney');

        expect($check['status'])->toBe('warning')
            ->and($check['title'])->toBe('You are named as your own attorney')
            ->and($check['description'])->toContain('contradiction Fynla cannot resolve for you')
            ->and($check['description'])->not->toContain('cannot confer');
    });

    it('warns when the donor is named as their own certificate provider', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'donor_full_name' => 'Patricia Bennett',
            'certificate_provider_name' => 'Patricia Bennett',
        ]);

        $check = $partyCheck($this->service->checkCompliance($lpa), 'party_roles_donor_certificate_provider');

        expect($check['status'])->toBe('warning')
            ->and($check['description'])->toContain('Schedule 1, paragraph 2(1)(e)')
            ->and($check['description'])->toContain('contradiction Fynla cannot resolve for you');
    });

    it('warns when one person is both an attorney and a replacement attorney', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Harold Bennett',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'replacement',
            'full_name' => 'Harold Bennett',
        ]);

        $check = $partyCheck($this->service->checkCompliance($lpa), 'party_roles_attorney_and_replacement');

        expect($check['status'])->toBe('warning')
            ->and($check['title'])->toBe('Harold Bennett is named as both an attorney and a replacement attorney')
            ->and($check['description'])->toContain('would be replacing themselves');
    });

    it('warns when the same person is entered twice in one list', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create(['user_id' => $this->user->id]);
        LpaAttorney::factory(2)->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Harold Bennett',
        ]);

        $check = $partyCheck($this->service->checkCompliance($lpa), 'party_roles_duplicate_attorney');

        expect($check['status'])->toBe('warning')
            ->and($check['title'])->toBe('Harold Bennett is named twice');
    });

    // The half compliance flagged as most likely to be "tidied" into an object claim.
    it('passes with wording that describes the comparison, never the absence of a conflict', function () use ($partyCheck) {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'donor_full_name' => 'Patricia Bennett',
            'certificate_provider_name' => 'Dr Alice Okafor',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Harold Bennett',
        ]);

        $check = $partyCheck($this->service->checkCompliance($lpa), 'party_roles');

        expect($check['status'])->toBe('pass')
            ->and($check['title'])->toBe('The names in each role are different')
            ->and($check['description'])->toBe('The certificate provider and attorney names you entered do not match each other.')
            ->and(strtolower($check['title'].' '.$check['description']))->not->toContain('no conflict');
    });

    it('reports every conflict at once rather than one at a time', function () {
        $lpa = LastingPowerOfAttorney::factory()->propertyFinancial()->create([
            'user_id' => $this->user->id,
            'donor_full_name' => 'Patricia Bennett',
            'certificate_provider_name' => 'Patricia Bennett',
        ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Patricia Bennett',
        ]);

        $keys = collect($this->service->checkCompliance($lpa)['checks'])->pluck('key');

        expect($keys)->toContain('party_roles_certificate_provider_attorney')
            ->and($keys)->toContain('party_roles_donor_attorney')
            ->and($keys)->toContain('party_roles_donor_certificate_provider')
            ->and($keys->duplicates())->toBeEmpty();
    });
});

/**
 * W-0104 — every attorney must be 18 or older.
 *
 * Mental Capacity Act 2005 s10(1)(a) sets the minimum age for an attorney, and
 * the same statute sets the donor's. Only the donor's was checked, though
 * `lpa_attorneys.date_of_birth` is captured for every attorney — so a child
 * could be appointed and the instrument shown to the user as compliant right up
 * to the point the Office of the Public Guardian refused to register it.
 *
 * The donor check reading as though it covered "the age requirement" is most of
 * why this survived.
 */
describe('attorney ages (W-0104)', function () {
    $ageCheck = fn (array $result): array => collect($result['checks'])
        ->firstWhere('key', 'attorney_ages') ?? [];

    it('fails when an appointed attorney is under 18', function () use ($ageCheck) {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Alfie Jones',
            'date_of_birth' => now()->subYears(12),
        ]);

        $check = $ageCheck($this->service->checkCompliance($lpa->fresh()));

        expect($check['status'])->toBe('fail')
            ->and($check['description'])->toContain('Alfie Jones');
    });

    it('passes when every attorney is 18 or older', function () use ($ageCheck) {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'date_of_birth' => now()->subYears(40),
        ]);

        expect($ageCheck($this->service->checkCompliance($lpa->fresh()))['status'])->toBe('pass');
    });

    it('fails on a missing date of birth rather than passing quietly', function () use ($ageCheck) {
        // An attorney whose age cannot be established is exactly the case this
        // check exists for. Treating unknown as acceptable would reproduce the
        // defect for anyone who left the field blank.
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'date_of_birth' => null,
        ]);

        expect($ageCheck($this->service->checkCompliance($lpa->fresh()))['status'])->toBe('fail');
    });
});

/**
 * W-0105 — a bankrupt attorney cannot act for property and financial affairs.
 *
 * Mental Capacity Act 2005 s13(8)-(9). The question was never asked: no column,
 * no field, no check — so an instrument naming a bankrupt attorney was presented
 * as compliant and would have been refused registration by the Office of the
 * Public Guardian.
 *
 * The restriction is TYPE-DEPENDENT, which is why a blanket bar would be wrong:
 * a bankrupt person may act as attorney for health and welfare.
 */
describe('attorney bankruptcy (W-0105)', function () {
    $bankruptcyCheck = fn (array $result): array => collect($result['checks'])
        ->firstWhere('key', 'attorney_bankruptcy') ?? [];

    it('fails a property and financial affairs LPA naming a bankrupt attorney', function () use ($bankruptcyCheck) {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Marcus Webb',
            'is_bankrupt' => true,
        ]);

        $check = $bankruptcyCheck($this->service->checkCompliance($lpa->fresh()));

        expect($check['status'])->toBe('fail')
            ->and($check['description'])->toContain('Marcus Webb');
    });

    it('does NOT disqualify a bankrupt attorney on a health and welfare LPA', function () use ($bankruptcyCheck) {
        // s13(8) applies to property and financial affairs only. Refusing them
        // here would invent a restriction the statute does not impose.
        $lpa = LastingPowerOfAttorney::factory()
            ->healthWelfare()
            ->create(['user_id' => $this->user->id]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'is_bankrupt' => true,
        ]);

        expect($bankruptcyCheck($this->service->checkCompliance($lpa->fresh()))['status'])->toBe('pass');
    });

    it('warns rather than fails when the question has not been answered', function () use ($bankruptcyCheck) {
        // The donor may simply not have been asked, and the application has only
        // just begun asking. Treating silence as a breach would fail every
        // instrument created before the field existed.
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'is_bankrupt' => null,
        ]);

        expect($bankruptcyCheck($this->service->checkCompliance($lpa->fresh()))['status'])->toBe('warning');
    });

    it('passes when every attorney is confirmed not bankrupt', function () use ($bankruptcyCheck) {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'is_bankrupt' => false,
        ]);

        expect($bankruptcyCheck($this->service->checkCompliance($lpa->fresh()))['status'])->toBe('pass');
    });
});

/**
 * W-0107 — the consequence of having no replacement attorney depends on HOW the
 * attorneys were appointed.
 *
 * The warning said the instrument "may become invalid if ALL primary attorneys
 * are unable to serve". Under MCA 2005 s10(4) that is true only of a JOINTLY AND
 * SEVERALLY appointment, where the survivors carry on. Where attorneys act
 * JOINTLY, the failure of a SINGLE one ends the entire appointment — so the
 * warning told the donor with the most to lose that they were the safest.
 */
describe('replacement attorney consequence (W-0107)', function () {
    $replacementCheck = fn (array $result): array => collect($result['checks'])
        ->firstWhere('key', 'replacement_attorneys') ?? [];

    it('warns a jointly-appointed donor that ONE failure ends the whole appointment', function () use ($replacementCheck) {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'attorney_decision_type' => 'jointly',
            ]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
        ]);

        $check = $replacementCheck($this->service->checkCompliance($lpa->fresh()));

        expect($check['status'])->toBe('warning')
            ->and($check['description'])->toContain('any ONE')
            ->and($check['description'])->not->toContain('jointly and severally');
    });

    it('tells a jointly-and-severally donor the others can continue', function () use ($replacementCheck) {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'attorney_decision_type' => 'jointly_and_severally',
            ]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
        ]);

        $check = $replacementCheck($this->service->checkCompliance($lpa->fresh()));

        expect($check['description'])->toContain('jointly and severally')
            ->and($check['description'])->toContain('others can continue');
    });

    it('treats jointly-for-some as joint, because the joint limb behaves that way', function () use ($replacementCheck) {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'attorney_decision_type' => 'jointly_for_some',
            ]);

        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
        ]);

        expect($replacementCheck($this->service->checkCompliance($lpa->fresh()))['description'])
            ->toContain('any ONE');
    });
});

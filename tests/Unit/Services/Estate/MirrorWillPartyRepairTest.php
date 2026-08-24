<?php

declare(strict_types=1);

use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\User;
use App\Services\Estate\WillDocumentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0395 — the backfill half of W-0024.
 *
 * W-0024 fixed the mirror GENERATOR: every party list is now swapped, not just
 * the residuary. It could not fix documents already generated. A mirror created
 * before it landed carries the primary's executors and guardians verbatim, so
 * the partner's own will appoints HER as her own executor and describes her as
 * her own spouse — and `markComplete()` derived `wills.executor_name` from that
 * list, so the wrong names were persisted into Fynla's record of the
 * household's intentions.
 *
 * The verdict on the live household, evidenced rather than inferred: a fresh
 * mirror generated through the live service on 2026-08-23 swapped every party
 * correctly, so W-0024 is fixed and `will_documents.id = 6` is pre-fix residue.
 * These cases lock the repair that clears the residue.
 *
 * NO SYMMETRIC FIXTURES. The repair's whole risk is direction: run the
 * generator's bidirectional swap over an already-correct document and it
 * exchanges the partners back, making the PRIMARY his own executor — a repair
 * that manufactures the defect it repairs. Both directions are asserted, on the
 * same pair, in every case below.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(WillDocumentService::class);
});

/**
 * A married pair with distinguishable names, and no shared substring that
 * `isSameParty()` could confuse.
 *
 * @return array{0: User, 1: User}
 */
function repairCouple(): array
{
    $primary = User::factory()->create([
        'first_name' => 'David',
        'surname' => 'Jones',
        'marital_status' => 'married',
    ]);
    $partner = User::factory()->create([
        'first_name' => 'Sarah',
        'surname' => 'Jones',
        'marital_status' => 'married',
        'spouse_id' => $primary->id,
    ]);
    $primary->update(['spouse_id' => $partner->id]);

    return [$primary, $partner];
}

/**
 * The shape a pre-W-0024 mirror actually has: testator is the PARTNER, and every
 * party list still names the partner because it was copied from the primary,
 * whose will correctly named her.
 */
function brokenMirrorFor(User $partner): WillDocument
{
    $will = Will::create(['user_id' => $partner->id, 'has_will' => true]);

    $doc = WillDocument::create([
        'user_id' => $partner->id,
        'will_id' => $will->id,
        'will_type' => 'mirror',
        'status' => 'complete',
        'testator_full_name' => 'Sarah Jones',
        'testator_address' => 'The Willows, Guildford',
        'testator_date_of_birth' => '1978-04-22',
        'testator_occupation' => 'General Practitioner',
        'executors' => [
            ['name' => 'Sarah Jones', 'address' => 'The Willows', 'relationship' => 'Spouse'],
            ['name' => 'Barclays Wealth', 'address' => '1 Churchill Place', 'relationship' => 'Professional Executor'],
        ],
        'guardians' => [['name' => 'Sarah Jones', 'relationship' => 'Spouse']],
        'residuary_estate' => [['percentage' => 100, 'beneficiary_name' => 'David Jones']],
    ]);

    $will->update(['will_document_id' => $doc->id, 'executor_name' => WillDocumentService::executorNameFor($doc)]);

    return $doc->fresh();
}

/** A correct will: the testator is the primary, and he names his partner. */
function correctWillFor(User $primary): WillDocument
{
    $will = Will::create(['user_id' => $primary->id, 'has_will' => true]);

    $doc = WillDocument::create([
        'user_id' => $primary->id,
        'will_id' => $will->id,
        'will_type' => 'mirror',
        'status' => 'complete',
        'testator_full_name' => 'David Jones',
        'testator_address' => 'The Willows, Guildford',
        'testator_date_of_birth' => '1975-11-02',
        'testator_occupation' => 'Chief Financial Officer',
        'executors' => [
            ['name' => 'Sarah Jones', 'address' => 'The Willows', 'relationship' => 'Spouse'],
            ['name' => 'Barclays Wealth', 'address' => '1 Churchill Place', 'relationship' => 'Professional Executor'],
        ],
        'guardians' => [['name' => 'Sarah Jones', 'relationship' => 'Spouse']],
        'residuary_estate' => [['percentage' => 100, 'beneficiary_name' => 'Sarah Jones']],
    ]);

    $will->update(['will_document_id' => $doc->id, 'executor_name' => WillDocumentService::executorNameFor($doc)]);

    return $doc->fresh();
}

describe('repairSelfNamedParties', function () {
    it('makes the partner the executor of a will that appointed its own testator', function () {
        [, $partner] = repairCouple();
        $doc = brokenMirrorFor($partner);

        expect($this->service->repairSelfNamedParties($doc))->toBeTrue();

        $fresh = $doc->fresh();

        expect($fresh->executors[0]['name'])->toBe('David Jones')
            ->and($fresh->executors[0]['relationship'])->toBe('Spouse')
            // A third-party executor is not a party to the marriage and is left
            // exactly as recorded, name and relationship alike.
            ->and($fresh->executors[1]['name'])->toBe('Barclays Wealth')
            ->and($fresh->executors[1]['relationship'])->toBe('Professional Executor');
    });

    it('repairs the guardian appointment too', function () {
        [, $partner] = repairCouple();
        $doc = brokenMirrorFor($partner);

        $this->service->repairSelfNamedParties($doc);

        expect($doc->fresh()->guardians[0]['name'])->toBe('David Jones');
    });

    it('leaves a residuary beneficiary that was already correct alone', function () {
        // Sarah's will correctly leaves everything to David. The repair must not
        // touch it — replacing a correct partner name would leave her estate to
        // herself.
        [, $partner] = repairCouple();
        $doc = brokenMirrorFor($partner);

        $this->service->repairSelfNamedParties($doc);

        expect($doc->fresh()->residuary_estate[0]['beneficiary_name'])->toBe('David Jones');
    });

    it('rewrites the executor name the will planning screen actually reads', function () {
        // The document was only half the damage. `wills.executor_name` is what
        // renders on /estate, and repairing the document without it leaves the
        // screen showing the defect that was just fixed.
        [, $partner] = repairCouple();
        $doc = brokenMirrorFor($partner);

        expect(Will::where('user_id', $partner->id)->value('executor_name'))
            ->toBe('Sarah Jones, Barclays Wealth');

        $this->service->repairSelfNamedParties($doc);

        expect(Will::where('user_id', $partner->id)->value('executor_name'))
            ->toBe('David Jones, Barclays Wealth');
    });

    it('does not touch a will that names its partner correctly', function () {
        // The direction guard. Running the generator's bidirectional swap here
        // would produce "David Jones, Barclays Wealth" on DAVID's own will — the
        // exact defect, manufactured by the repair.
        [$primary] = repairCouple();
        $doc = correctWillFor($primary);

        expect($this->service->repairSelfNamedParties($doc))->toBeFalse();

        $fresh = $doc->fresh();

        expect($fresh->executors[0]['name'])->toBe('Sarah Jones')
            ->and($fresh->residuary_estate[0]['beneficiary_name'])->toBe('Sarah Jones')
            ->and(Will::where('user_id', $primary->id)->value('executor_name'))
            ->toBe('Sarah Jones, Barclays Wealth');
    });

    it('repairs one side of a pair and leaves the other side untouched', function () {
        // Both directions on ONE household, which is what the live defect looked
        // like: one broken will and one correct will, on the same two names.
        [$primary, $partner] = repairCouple();
        $broken = brokenMirrorFor($partner);
        $correct = correctWillFor($primary);

        $this->service->repairSelfNamedParties($broken);
        $this->service->repairSelfNamedParties($correct);

        $partnerExecutors = Will::where('user_id', $partner->id)->value('executor_name');
        $primaryExecutors = Will::where('user_id', $primary->id)->value('executor_name');

        expect($partnerExecutors)->toBe('David Jones, Barclays Wealth')
            ->and($primaryExecutors)->toBe('Sarah Jones, Barclays Wealth')
            // Nobody is their own executor, and the two wills say different
            // things — which they could not while one list was copied to both.
            ->and($partnerExecutors)->not->toBe($primaryExecutors);
    });

    it('changes nothing on a second run', function () {
        [, $partner] = repairCouple();
        $doc = brokenMirrorFor($partner);

        expect($this->service->repairSelfNamedParties($doc))->toBeTrue();
        expect($this->service->repairSelfNamedParties($doc->fresh()))->toBeFalse();

        expect($doc->fresh()->executors[0]['name'])->toBe('David Jones');
    });

    it('leaves an unmarried testator alone rather than guessing at a partner', function () {
        $single = User::factory()->create([
            'first_name' => 'Alex',
            'surname' => 'Chen',
            'marital_status' => 'single',
            'spouse_id' => null,
        ]);

        $will = Will::create(['user_id' => $single->id, 'has_will' => true]);
        $doc = WillDocument::create([
            'user_id' => $single->id,
            'will_id' => $will->id,
            'will_type' => 'simple',
            'status' => 'complete',
            'testator_full_name' => 'Alex Chen',
            'executors' => [['name' => 'Alex Chen', 'relationship' => 'Self']],
        ]);

        expect($this->service->repairSelfNamedParties($doc))->toBeFalse();
        expect($doc->fresh()->executors[0]['name'])->toBe('Alex Chen');
    });
});

describe('estate:backfill-mirror-parties', function () {
    it('reports what it would do and writes nothing without --force', function () {
        [, $partner] = repairCouple();
        brokenMirrorFor($partner);

        $this->artisan('estate:backfill-mirror-parties')
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        // The side effect, not the exit code: a dry run that quietly wrote would
        // still return 0.
        expect(Will::where('user_id', $partner->id)->value('executor_name'))
            ->toBe('Sarah Jones, Barclays Wealth');
    });

    it('applies the repair with --force', function () {
        [, $partner] = repairCouple();
        brokenMirrorFor($partner);

        $this->artisan('estate:backfill-mirror-parties --force')->assertSuccessful();

        expect(Will::where('user_id', $partner->id)->value('executor_name'))
            ->toBe('David Jones, Barclays Wealth');
    });

    it('finds nothing to repair in a household whose wills are already right', function () {
        [$primary] = repairCouple();
        correctWillFor($primary);

        $this->artisan('estate:backfill-mirror-parties --force')
            ->expectsOutputToContain('Nothing to repair')
            ->assertSuccessful();

        expect(Will::where('user_id', $primary->id)->value('executor_name'))
            ->toBe('Sarah Jones, Barclays Wealth');
    });
});

/**
 * W-0396 — found by writing the tests above, not by the persona.
 *
 * The generator matched each partner on ONE spelling, and built the two sides
 * from different sources: the primary's from the will's `testator_full_name`,
 * the partner's from first + middle + surname off the profile. A partner with a
 * middle name RECORDED but named in the will WITHOUT it matched neither, so the
 * swap found nothing to do and produced exactly the document W-0024 fixed — a
 * will appointing its own testator as executor.
 *
 * W-0024's own tests could not see it. Their fixtures give the partner no middle
 * name, so the two spellings coincide: the right answer and the wrong answer are
 * the same string (tests/CLAUDE.md §4, Collision). The peak_earners household
 * has no middle names either, so no amount of testing on this persona would have
 * found it.
 */
describe('the partner is recognised under every spelling their own records hold (W-0396)', function () {
    it('swaps the executor when the will omits the partner\'s recorded middle name', function () {
        $primary = User::factory()->create([
            'first_name' => 'David',
            'middle_name' => null,
            'surname' => 'Jones',
            'marital_status' => 'married',
        ]);
        $partner = User::factory()->create([
            'first_name' => 'Sarah',
            // Recorded on the profile...
            'middle_name' => 'Louise',
            'surname' => 'Jones',
            'marital_status' => 'married',
            'spouse_id' => $primary->id,
        ]);
        $primary->update(['spouse_id' => $partner->id]);

        $primaryDoc = WillDocument::create([
            'user_id' => $primary->id,
            'will_type' => 'mirror',
            'status' => 'complete',
            'testator_full_name' => 'David Jones',
            'testator_address' => 'The Willows, Guildford',
            'testator_date_of_birth' => '1975-11-02',
            'testator_occupation' => 'Chief Financial Officer',
            // ...and omitted here, as a person writing their own will would.
            'executors' => [
                ['name' => 'Sarah Jones', 'address' => 'The Willows', 'relationship' => 'Spouse'],
                ['name' => 'Barclays Wealth', 'address' => '1 Churchill Place', 'relationship' => 'Professional Executor'],
            ],
            'guardians' => [['name' => 'Sarah Jones', 'relationship' => 'Spouse']],
            'residuary_estate' => [['percentage' => 100, 'beneficiary_name' => 'Sarah Jones']],
        ]);

        $mirror = app(WillDocumentService::class)->generateMirrorWill($primaryDoc->fresh());

        expect($mirror->testator_full_name)->toBe('Sarah Louise Jones')
            // The whole point: before this, the mirror kept "Sarah Jones" here
            // and Sarah was appointed executor of Sarah's own will.
            ->and($mirror->executors[0]['name'])->toBe('David Jones')
            ->and($mirror->guardians[0]['name'])->toBe('David Jones')
            ->and($mirror->residuary_estate[0]['beneficiary_name'])->toBe('David Jones')
            ->and($mirror->executors[1]['name'])->toBe('Barclays Wealth');
    });

    it('recognises the partner under the will\'s spelling, the profile\'s, and the short form', function () {
        $person = User::factory()->create([
            'first_name' => 'Sarah',
            'middle_name' => 'Louise',
            'surname' => 'Jones',
        ]);

        $variants = WillDocumentService::nameVariants($person, 'Sarah L Jones');

        expect($variants[0])->toBe('Sarah L Jones')
            ->and($variants)->toContain('Sarah Louise Jones')
            ->and($variants)->toContain('Sarah Jones')
            // Nothing invented: no initials, no nicknames, no surname-only. Two
            // people can share a name and a legal document is the wrong place to
            // guess.
            ->and($variants)->not->toContain('Sarah')
            ->and($variants)->not->toContain('S Jones');
    });
});

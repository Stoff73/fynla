<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Estate\WillDocumentService;

beforeEach(function () {
    $this->service = app(WillDocumentService::class);
});

/**
 * The peak_earners mirror pair: David and Sarah Jones, married and reciprocally
 * linked, exactly as tests/Persona/peak_earners.md describes them.
 *
 * @return array{0: User, 1: User}
 */
function mirrorCouple(): array
{
    $spouse = User::factory()->create([
        'first_name' => 'Sarah',
        'middle_name' => null,
        'surname' => 'Jones',
        'marital_status' => 'married',
        'date_of_birth' => '1978-04-22',
    ]);

    $user = User::factory()->create([
        'first_name' => 'David',
        'middle_name' => null,
        'surname' => 'Jones',
        'marital_status' => 'married',
        'spouse_id' => $spouse->id,
    ]);

    $spouse->update(['spouse_id' => $user->id]);

    return [$user->fresh(), $spouse->fresh()];
}

describe('WillDocumentService', function () {
    describe('prePopulateData', function () {
        it('pre-populates testator details from user profile', function () {
            $user = User::factory()->create([
                'first_name' => 'James',
                'middle_name' => 'Andrew',
                'surname' => 'Carter',
                'address_line_1' => '42 Maple Drive',
                'city' => 'Guildford',
                'county' => 'Surrey',
                'postcode' => 'GU1 3AA',
                'date_of_birth' => '1985-06-15',
                'occupation' => 'Software Engineer',
                'domicile_status' => 'uk_domiciled',
                'marital_status' => 'married',
            ]);

            $data = $this->service->prePopulateData($user);

            expect($data['testator']['full_name'])->toBe('James Andrew Carter');
            expect($data['testator']['address'])->toContain('42 Maple Drive');
            expect($data['testator']['address'])->toContain('Guildford');
            expect($data['testator']['address'])->toContain('GU1 3AA');
            expect($data['testator']['date_of_birth'])->toBe('1985-06-15');
            expect($data['testator']['occupation'])->toBe('Software Engineer');
        });

        it('pre-populates children from family members', function () {
            $user = User::factory()->create();

            FamilyMember::factory()->create([
                'user_id' => $user->id,
                'relationship' => 'child',
                'first_name' => 'Oliver',
                'last_name' => 'Carter',
                'date_of_birth' => now()->subYears(10),
                'is_dependent' => true,
            ]);

            FamilyMember::factory()->create([
                'user_id' => $user->id,
                'relationship' => 'child',
                'first_name' => 'Sophie',
                'last_name' => 'Carter',
                'date_of_birth' => now()->subYears(25),
                'is_dependent' => false,
            ]);

            $data = $this->service->prePopulateData($user);

            expect($data['children'])->toHaveCount(2);
            expect($data['has_minor_children'])->toBeTrue();
            expect($data['children'][0]['full_name'])->toContain('Oliver');
            expect($data['children'][0]['is_minor'])->toBeTrue();
            expect($data['children'][1]['is_minor'])->toBeFalse();
        });

        it('pre-populates spouse for mirror will', function () {
            $spouse = User::factory()->create([
                'first_name' => 'Emily',
                'surname' => 'Carter',
                'date_of_birth' => '1987-03-22',
            ]);

            $user = User::factory()->create([
                'marital_status' => 'married',
                'spouse_id' => $spouse->id,
            ]);

            $data = $this->service->prePopulateData($user);

            expect($data['has_spouse'])->toBeTrue();
            expect($data['spouse'])->not->toBeNull();
            expect($data['spouse']['full_name'])->toContain('Emily');
            expect($data['spouse']['date_of_birth'])->toBe('1987-03-22');
        });

        it('includes existing executor name from wills table', function () {
            $user = User::factory()->create();
            Will::factory()->withWill()->create([
                'user_id' => $user->id,
                'executor_name' => 'Robert Jones',
            ]);

            $data = $this->service->prePopulateData($user);

            expect($data['existing_executor_name'])->toBe('Robert Jones');
        });

        it('handles user with no spouse or children', function () {
            $user = User::factory()->create([
                'marital_status' => 'single',
                'spouse_id' => null,
            ]);

            $data = $this->service->prePopulateData($user);

            expect($data['has_spouse'])->toBeFalse();
            expect($data['spouse'])->toBeNull();
            expect($data['children'])->toBeEmpty();
            expect($data['has_minor_children'])->toBeFalse();
        });
    });

    describe('createDraft', function () {
        it('creates a new draft will document', function () {
            $user = User::factory()->create();

            $doc = $this->service->createDraft($user, [
                'will_type' => 'simple',
                'testator_full_name' => 'James Carter',
                'domicile_confirmed' => 'england_wales',
            ]);

            expect($doc)->toBeInstanceOf(WillDocument::class);
            expect($doc->status)->toBe('draft');
            expect($doc->will_type)->toBe('simple');
            expect($doc->testator_full_name)->toBe('James Carter');
            expect($doc->user_id)->toBe($user->id);
        });

        it('links to existing will record', function () {
            $user = User::factory()->create();
            $will = Will::factory()->withWill()->create(['user_id' => $user->id]);

            $doc = $this->service->createDraft($user, [
                'will_type' => 'simple',
                'testator_full_name' => 'James Carter',
            ]);

            expect($doc->will_id)->toBe($will->id);
        });
    });

    describe('updateStep', function () {
        it('saves executor data for the executors step', function () {
            $doc = WillDocument::factory()->create();

            $executors = [
                ['name' => 'John Smith', 'address' => '10 High St', 'relationship' => 'Brother', 'phone' => '07700900000'],
            ];

            $updated = $this->service->updateStep($doc, 'executors', ['executors' => $executors]);

            expect($updated->executors)->toHaveCount(1);
            expect($updated->executors[0]['name'])->toBe('John Smith');
            expect($updated->last_edited_at)->not->toBeNull();
        });

        it('saves residuary estate data', function () {
            $doc = WillDocument::factory()->create();

            $residuary = [
                ['beneficiary_name' => 'Emily Carter', 'percentage' => 60, 'substitution_beneficiary' => ''],
                ['beneficiary_name' => 'Oliver Carter', 'percentage' => 40, 'substitution_beneficiary' => 'Their children'],
            ];

            $updated = $this->service->updateStep($doc, 'residuary', ['residuary_estate' => $residuary]);

            expect($updated->residuary_estate)->toHaveCount(2);
            expect($updated->residuary_estate[0]['percentage'])->toBe(60);
        });

        it('saves funeral preferences', function () {
            $doc = WillDocument::factory()->create();

            $updated = $this->service->updateStep($doc, 'funeral', [
                'funeral_preference' => 'cremation',
                'funeral_wishes_notes' => 'Scatter ashes at sea',
            ]);

            expect($updated->funeral_preference)->toBe('cremation');
            expect($updated->funeral_wishes_notes)->toBe('Scatter ashes at sea');
        });
    });

    describe('validateDocument', function () {
        it('returns error when no executors', function () {
            $doc = WillDocument::factory()->create([
                'executors' => [],
                'residuary_estate' => [['beneficiary_name' => 'Test', 'percentage' => 100]],
            ]);

            $warnings = $this->service->validateDocument($doc);

            $executorErrors = collect($warnings)->where('field', 'executors')->where('severity', 'error');
            expect($executorErrors)->not->toBeEmpty();
        });

        it('returns error when residuary percentages do not sum to 100', function () {
            $doc = WillDocument::factory()->create([
                'residuary_estate' => [
                    ['beneficiary_name' => 'A', 'percentage' => 60],
                    ['beneficiary_name' => 'B', 'percentage' => 30],
                ],
            ]);

            $warnings = $this->service->validateDocument($doc);

            $residuaryErrors = collect($warnings)->where('field', 'residuary_estate')->where('severity', 'error');
            expect($residuaryErrors)->not->toBeEmpty();
            expect($residuaryErrors->first()['message'])->toContain('90%');
        });

        it('returns no errors for a valid document', function () {
            $doc = WillDocument::factory()->create([
                'executors' => [['name' => 'John Smith', 'address' => '10 High St']],
                'residuary_estate' => [['beneficiary_name' => 'Emily Carter', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(40),
                'domicile_confirmed' => 'england_wales',
            ]);

            $warnings = $this->service->validateDocument($doc);

            $errors = collect($warnings)->where('severity', 'error');
            expect($errors)->toBeEmpty();
        });

        it('warns about minor children without guardians', function () {
            $user = User::factory()->create();
            FamilyMember::factory()->create([
                'user_id' => $user->id,
                'relationship' => 'child',
                'date_of_birth' => now()->subYears(5),
            ]);

            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'guardians' => [],
                'executors' => [['name' => 'Test', 'address' => 'Addr']],
                'residuary_estate' => [['beneficiary_name' => 'Test', 'percentage' => 100]],
            ]);

            $warnings = $this->service->validateDocument($doc);

            $guardianWarnings = collect($warnings)->where('field', 'guardians')->where('severity', 'warning');
            expect($guardianWarnings)->not->toBeEmpty();
        });

        it('returns error for testator under 18', function () {
            $doc = WillDocument::factory()->create([
                'testator_date_of_birth' => now()->subYears(16),
                'executors' => [['name' => 'Test', 'address' => 'Addr']],
                'residuary_estate' => [['beneficiary_name' => 'Test', 'percentage' => 100]],
            ]);

            $warnings = $this->service->validateDocument($doc);

            $ageErrors = collect($warnings)->where('field', 'personal')->where('severity', 'error');
            expect($ageErrors)->not->toBeEmpty();
        });

        it('warns about non-England/Wales domicile', function () {
            $doc = WillDocument::factory()->create([
                'domicile_confirmed' => 'scotland',
                'executors' => [['name' => 'Test', 'address' => 'Addr']],
                'residuary_estate' => [['beneficiary_name' => 'Test', 'percentage' => 100]],
            ]);

            $warnings = $this->service->validateDocument($doc);

            $domicileWarnings = collect($warnings)->where('field', 'domicile');
            expect($domicileWarnings)->not->toBeEmpty();
        });
    });

    describe('markComplete', function () {
        it('marks a valid document as complete and syncs wills table', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [['name' => 'John Smith', 'address' => '10 High St']],
                'residuary_estate' => [['beneficiary_name' => 'Emily', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(40),
                'domicile_confirmed' => 'england_wales',
            ]);

            $completed = $this->service->markComplete($doc);

            expect($completed->status)->toBe('complete');
            expect($completed->generated_at)->not->toBeNull();

            // Check wills table was synced
            $will = Will::where('user_id', $user->id)->first();
            expect($will)->not->toBeNull();
            expect($will->has_will)->toBeTrue();
            expect($will->will_document_id)->toBe($completed->id);
        });

        it('throws exception for document with validation errors', function () {
            $doc = WillDocument::factory()->create([
                'executors' => [],
                'residuary_estate' => [],
            ]);

            expect(fn () => $this->service->markComplete($doc))
                ->toThrow(RuntimeException::class);
        });
    });

    describe('generateMirrorWill', function () {
        it('generates a mirror will with swapped beneficiaries', function () {
            $spouse = User::factory()->create([
                'first_name' => 'Emily',
                'middle_name' => null,
                'surname' => 'Carter',
                'date_of_birth' => '1987-03-22',
            ]);

            $user = User::factory()->create([
                'first_name' => 'James',
                'surname' => 'Carter',
                'spouse_id' => $spouse->id,
            ]);
            // W-0350 — reciprocal. A mirror will is written INTO the spouse's account,
            // so it now needs a link both parties made, not one this account claimed.
            $spouse->update(['spouse_id' => $user->id]);

            $primary = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'testator_full_name' => 'James Carter',
                'residuary_estate' => [
                    ['beneficiary_name' => 'Emily Carter', 'percentage' => 100, 'substitution_beneficiary' => ''],
                ],
            ]);

            $mirror = $this->service->generateMirrorWill($primary);

            expect($mirror->user_id)->toBe($spouse->id);
            expect($mirror->will_type)->toBe('mirror');
            expect($mirror->testator_full_name)->toBe('Emily Carter');
            expect($mirror->mirror_document_id)->toBe($primary->id);

            // Check primary was linked back
            $primary->refresh();
            expect($primary->mirror_document_id)->toBe($mirror->id);

            // Beneficiary should be swapped
            expect($mirror->residuary_estate[0]['beneficiary_name'])->toBe('James Carter');
        });

        it('throws exception when no spouse found', function () {
            $user = User::factory()->create(['spouse_id' => null]);
            $doc = WillDocument::factory()->create(['user_id' => $user->id]);

            expect(fn () => $this->service->generateMirrorWill($doc))
                ->toThrow(RuntimeException::class, 'no reciprocally linked spouse');
        });
    });

    describe('getForUser', function () {
        it('returns the most recent will document for a user', function () {
            $user = User::factory()->create();

            WillDocument::factory()->create([
                'user_id' => $user->id,
                'updated_at' => now()->subDay(),
            ]);

            $latest = WillDocument::factory()->create([
                'user_id' => $user->id,
                'updated_at' => now(),
            ]);

            $result = $this->service->getForUser($user);

            expect($result->id)->toBe($latest->id);
        });

        it('returns null when no document exists', function () {
            $user = User::factory()->create();

            $result = $this->service->getForUser($user);

            expect($result)->toBeNull();
        });
    });
    describe('mirror wills swap every party, not just the residuary (W-0024)', function () {
        it('makes each partner the other\'s executor', function () {
            [$user, $spouse] = mirrorCouple();

            $primary = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'testator_full_name' => 'David Jones',
                'executors' => [
                    ['name' => 'Sarah Jones', 'address' => 'The Willows', 'relationship' => 'Spouse'],
                    ['name' => 'Barclays Wealth', 'address' => '1 Churchill Place', 'relationship' => 'Professional'],
                ],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
            ]);

            $mirror = $this->service->generateMirrorWill($primary);

            expect($mirror->testator_full_name)->toBe('Sarah Jones');
            expect($mirror->executors[0]['name'])->toBe('David Jones');
            expect($mirror->executors[0]['relationship'])->toBe('Spouse');
            // Third parties are carried across untouched.
            expect($mirror->executors[1]['name'])->toBe('Barclays Wealth');
            expect($mirror->executors[1]['relationship'])->toBe('Professional');
            expect($mirror->residuary_estate[0]['beneficiary_name'])->toBe('David Jones');
        });

        it('never leaves a will appointing its own testator as executor', function () {
            [$user, $spouse] = mirrorCouple();

            $primary = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'testator_full_name' => 'David Jones',
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows', 'relationship' => 'Spouse']],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
            ]);

            $mirror = $this->service->generateMirrorWill($primary);

            $names = array_column($mirror->executors, 'name');
            expect($names)->not->toContain($mirror->testator_full_name);
        });

        it('swaps guardians too', function () {
            [$user, $spouse] = mirrorCouple();

            $primary = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'testator_full_name' => 'David Jones',
                'guardians' => [['name' => 'Sarah Jones', 'relationship' => 'Spouse']],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
            ]);

            $mirror = $this->service->generateMirrorWill($primary);

            expect($mirror->guardians[0]['name'])->toBe('David Jones');
        });

        it('flags gifts copied from the partner so a charitable legacy is not silently inherited', function () {
            [$user, $spouse] = mirrorCouple();

            $primary = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'testator_full_name' => 'David Jones',
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'specific_gifts' => [
                    ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
                ],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            $mirror = $this->service->generateMirrorWill($primary);

            expect($mirror->specific_gifts[0]['copied_from_partner'])->toBeTrue();

            $messages = array_column($this->service->validateDocument($mirror), 'message');
            expect($messages)->toContain(WillDocumentService::COPIED_GIFTS_MESSAGE);

            // Saving the gifts step IS the review — the flag and the warning go.
            $reviewed = $this->service->updateStep($mirror, 'gifts', [
                'specific_gifts' => [
                    ['beneficiary_name' => 'British Heart Foundation', 'type' => 'cash', 'amount' => 10000],
                ],
            ]);

            expect($reviewed->specific_gifts[0])->not->toHaveKey('copied_from_partner');
            expect(array_column($this->service->validateDocument($reviewed), 'message'))
                ->not->toContain(WillDocumentService::COPIED_GIFTS_MESSAGE);
        });

        it('offers guardians on both wills when the children sit on the other account', function () {
            [$user, $spouse] = mirrorCouple();

            FamilyMember::factory()->create([
                'user_id' => $user->id,
                'relationship' => 'child',
                'first_name' => 'Charlotte',
                'last_name' => 'Jones',
                'date_of_birth' => now()->subYears(16),
            ]);

            // The partner holds no FamilyMember rows of her own.
            expect($this->service->prePopulateData($spouse)['has_minor_children'])->toBeTrue();
        });

        it('blocks completion when an executor is the testator, on any path', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'testator_full_name' => 'Sarah Jones',
                'executors' => [['name' => 'sarah  jones', 'address' => 'The Willows']],
                'residuary_estate' => [['beneficiary_name' => 'David Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            expect(fn () => $this->service->markComplete($doc))
                ->toThrow(RuntimeException::class, WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE);
        });
    });

    describe('completing a will records its gifts as bequests (W-0023)', function () {
        it('creates a Bequest row for a charitable cash legacy', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'specific_gifts' => [
                    ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000, 'conditions' => null],
                    ['beneficiary_name' => 'William Jones', 'type' => 'item', 'description' => 'My grandfather clock'],
                ],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            $this->service->markComplete($doc);

            $bequests = Bequest::where('user_id', $user->id)->orderBy('priority_order')->get();

            expect($bequests)->toHaveCount(2);
            expect($bequests[0]->beneficiary_name)->toBe('Cancer Research UK');
            expect($bequests[0]->bequest_type)->toBe('specific_amount');
            expect((float) $bequests[0]->specific_amount)->toBe(10000.0);
            expect($bequests[0]->will_document_id)->toBe($doc->id);
            expect($bequests[1]->bequest_type)->toBe('specific_asset');
            expect($bequests[1]->specific_asset_description)->toBe('My grandfather clock');
        });

        it('updates rather than duplicates when a will is re-completed', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'specific_gifts' => [['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000]],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            $this->service->markComplete($doc);
            $this->service->updateStep($doc, 'gifts', [
                'specific_gifts' => [['beneficiary_name' => 'British Heart Foundation', 'type' => 'cash', 'amount' => 12000]],
            ]);
            $this->service->markComplete($doc->fresh());

            $bequests = Bequest::where('user_id', $user->id)->get();

            expect($bequests)->toHaveCount(1);
            expect($bequests[0]->beneficiary_name)->toBe('British Heart Foundation');
            expect((float) $bequests[0]->specific_amount)->toBe(12000.0);
        });

        it('never touches bequests the user created by hand', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'specific_gifts' => [['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000]],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            $this->service->markComplete($doc);
            $will = Will::where('user_id', $user->id)->firstOrFail();

            $manual = Bequest::create([
                'will_id' => $will->id,
                'user_id' => $user->id,
                'beneficiary_name' => 'Macmillan Cancer Support',
                'bequest_type' => 'percentage',
                'percentage_of_estate' => 5,
                'priority_order' => 9,
            ]);

            $this->service->markComplete($doc->fresh());

            expect(Bequest::find($manual->id))->not->toBeNull();
            expect(Bequest::where('user_id', $user->id)->count())->toBe(2);
        });

        it('leaves residuary beneficiaries as document-only, by decision', function () {
            $user = User::factory()->create();
            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'specific_gifts' => [],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            $this->service->markComplete($doc);

            expect(Bequest::where('user_id', $user->id)->count())->toBe(0);
            // Because a residuary row could only be stored as a `percentage`,
            // which getNonSpouseAllocationPercentage() sums.
            $will = Will::where('user_id', $user->id)->firstOrFail();
            expect($will->getNonSpouseAllocationPercentage())->toBe(0.0);
        });
    });

    describe('married users may only complete a mirror will (W-0019)', function () {
        it('refuses to complete a simple will for a married user', function () {
            [$user] = mirrorCouple();

            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'simple',
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            expect(fn () => $this->service->markComplete($doc))
                ->toThrow(RuntimeException::class, 'qualified solicitor');
        });

        it('completes a mirror will for the same user', function () {
            [$user] = mirrorCouple();

            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'testator_full_name' => 'David Jones',
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            expect($this->service->markComplete($doc)->status)->toBe('complete');
        });
    });
    describe('a mirror will is a pair, and stays completable into one (W-0053)', function () {
        it('warns while the counterpart has not been generated', function () {
            [$user] = mirrorCouple();

            $primary = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'mirror_document_id' => null,
                'testator_full_name' => 'David Jones',
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            $messages = array_column($this->service->validateDocument($primary), 'message');
            expect($messages)->toContain(WillDocumentService::MIRROR_NOT_GENERATED_MESSAGE);

            $this->service->generateMirrorWill($primary);

            $after = array_column($this->service->validateDocument($primary->fresh()), 'message');
            expect($after)->not->toContain(WillDocumentService::MIRROR_NOT_GENERATED_MESSAGE);
        });

        it('does not warn on a simple will', function () {
            $user = User::factory()->create(['marital_status' => 'single']);

            $doc = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'simple',
                'mirror_document_id' => null,
                'executors' => [['name' => 'Someone Else', 'address' => '1 High St']],
                'residuary_estate' => [['beneficiary_name' => 'Someone Else', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            expect(array_column($this->service->validateDocument($doc), 'message'))
                ->not->toContain(WillDocumentService::MIRROR_NOT_GENERATED_MESSAGE);
        });

        it('can still generate the counterpart after the will is completed', function () {
            [$user] = mirrorCouple();

            $primary = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'mirror_document_id' => null,
                'testator_full_name' => 'David Jones',
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
                'testator_date_of_birth' => now()->subYears(48),
                'domicile_confirmed' => 'england_wales',
            ]);

            $completed = $this->service->markComplete($primary);
            expect($completed->status)->toBe('complete');
            expect($completed->mirror_document_id)->toBeNull();

            // The rescue path: a will stranded at completion can still be paired.
            $mirror = $this->service->generateMirrorWill($completed);

            expect($mirror->testator_full_name)->toBe('Sarah Jones');
            expect($primary->fresh()->mirror_document_id)->toBe($mirror->id);
        });

        it('returns the existing counterpart instead of creating a second', function () {
            [$user] = mirrorCouple();

            $primary = WillDocument::factory()->create([
                'user_id' => $user->id,
                'will_type' => 'mirror',
                'testator_full_name' => 'David Jones',
                'executors' => [['name' => 'Sarah Jones', 'address' => 'The Willows']],
                'residuary_estate' => [['beneficiary_name' => 'Sarah Jones', 'percentage' => 100]],
            ]);

            $first = $this->service->generateMirrorWill($primary);
            $second = $this->service->generateMirrorWill($primary->fresh());

            expect($second->id)->toBe($first->id);
            expect(WillDocument::where('user_id', $first->user_id)->count())->toBe(1);
        });
    });
});

/**
 * W-0153. The refusal opened "A will cannot appoint its own testator as executor" —
 * a legal rule in Fynla's own voice with no source, sitting beside a
 * powers-of-attorney equivalent that cites its Act and paragraph. Nothing required
 * a legal statement in user-facing copy to carry its source, so the two instruments
 * diverged in silence.
 *
 * This test is the requirement, not the wording: whoever rewrites the sentence has to
 * keep it checkable, and has to keep it a description of the office rather than an
 * invented prohibition.
 */
describe('a legal statement in user-facing copy carries its source (W-0153)', function () {
    it('attributes the executor rule to the provision that defines the office', function () {
        expect(WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE)
            ->toContain('Administration of Estates Act 1925, section 25');
    });

    it('describes what an executor is rather than asserting an unattributable ban', function () {
        expect(WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE)
            ->not->toContain('A will cannot appoint')
            ->and(WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE)
            ->toContain('administers it after the testator has died');
    });

    it('still tells the user what to do about it', function () {
        expect(WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE)
            ->toContain('Name the person who will carry out your wishes.');
    });
});

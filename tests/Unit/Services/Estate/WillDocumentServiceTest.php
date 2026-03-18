<?php

declare(strict_types=1);

use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Estate\WillDocumentService;

beforeEach(function () {
    $this->service = new WillDocumentService;
});

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
                ->toThrow(\RuntimeException::class);
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
                ->toThrow(\RuntimeException::class, 'no spouse found');
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
});

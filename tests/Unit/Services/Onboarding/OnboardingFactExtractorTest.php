<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingFactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->extractor = new OnboardingFactExtractor;
});

describe('personal bucket', function () {
    it('extracts marital status from married phrasing', function () {
        $facts = $this->extractor->extract("I'm 40, married to Angela");
        expect($facts['personal']['marital_status'] ?? null)->toBe('married');
    });

    it('distinguishes civil partnership from marriage', function () {
        $facts = $this->extractor->extract("We're in a civil partnership");
        expect($facts['personal']['marital_status'] ?? null)->toBe('civil_partnership');
    });

    it('detects widowed / divorced variants', function () {
        expect($this->extractor->extract("I'm widowed")['personal']['marital_status'])->toBe('widowed')
            ->and($this->extractor->extract("I'm divorced")['personal']['marital_status'])->toBe('divorced');
    });

    it('extracts an age hint', function () {
        $facts = $this->extractor->extract("I'm 45 years old");
        expect($facts['personal']['age_hint'] ?? null)->toBe(45);
    });

    it('does not extract an age of 5 as a person age', function () {
        $facts = $this->extractor->extract("I'm aged 5");
        // Guard: age_hint must be 18+. 5 is below the range.
        expect($facts['personal']['age_hint'] ?? null)->toBeNull();
    });

    it('does not confuse "wife\'s sister" as spouse via relation-noun stop-list', function () {
        $facts = $this->extractor->extract('my wife sister angela is coming');
        // "sister" is in the spouse-name stop-list, so the extractor rejects
        // it. preg_match returns at the first trigger match ("wife sister")
        // and does not continue scanning to "angela", so the spouse bucket
        // stays empty.
        expect($facts['spouse']['first_name'] ?? null)->toBeNull();
    });
});

describe('spouse bucket', function () {
    it('extracts first name from "married to Angela"', function () {
        $facts = $this->extractor->extract('I am married to Angela');
        expect($facts['spouse']['first_name'] ?? null)->toBe('Angela');
    });

    it('extracts from "wife Emily, two kids"', function () {
        $facts = $this->extractor->extract('wife Emily, two kids');
        expect($facts['spouse']['first_name'] ?? null)->toBe('Emily');
    });

    it('extracts age hint after name', function () {
        $facts = $this->extractor->extract('married to Angela, 45');
        expect($facts['spouse']['age_hint'] ?? null)->toBe(45);
    });

    it('extracts email address', function () {
        $facts = $this->extractor->extract('her email is angela@example.com');
        expect($facts['spouse']['email'] ?? null)->toBe('angela@example.com');
    });

    it('extracts first name from lowercase phrasing', function () {
        // Chat input does not auto-capitalise; users routinely type "married
        // to angela" (lowercase). This was a regression caught in session 111
        // (Emma Student / conv #7) — the extractor previously required a
        // capitalised first letter.
        $facts = $this->extractor->extract('married to angela');
        expect($facts['spouse']['first_name'] ?? null)->toBe('Angela');
    });

    it('normalises mixed and upper case to title case', function () {
        expect($this->extractor->extract('married to ANGELA')['spouse']['first_name'] ?? null)->toBe('Angela')
            ->and($this->extractor->extract('married to AnGeLa')['spouse']['first_name'] ?? null)->toBe('Angela')
            ->and($this->extractor->extract('married to anGELa')['spouse']['first_name'] ?? null)->toBe('Angela');
    });

    it('extracts lowercase spouse name from CSJ regression message', function () {
        // Verbatim from ai_messages #18 on conv #7 (28 April 2026 18:37:55).
        $facts = $this->extractor->extract('30 october 1973, married to angela');
        expect($facts['spouse']['first_name'] ?? null)->toBe('Angela')
            ->and($facts['personal']['marital_status'] ?? null)->toBe('married')
            ->and($facts['personal']['date_of_birth'] ?? null)->toBe('1973-10-30');
    });

    it('rejects pronouns as spouse first name', function () {
        expect($this->extractor->extract('married to her')['spouse']['first_name'] ?? null)->toBeNull()
            ->and($this->extractor->extract('married to him')['spouse']['first_name'] ?? null)->toBeNull()
            ->and($this->extractor->extract('married to them')['spouse']['first_name'] ?? null)->toBeNull();
    });

    it('rejects relation nouns as spouse first name', function () {
        // Trigger word followed by a relation noun = ambiguous. Reject.
        expect($this->extractor->extract('married to mother')['spouse']['first_name'] ?? null)->toBeNull()
            ->and($this->extractor->extract('wife sister')['spouse']['first_name'] ?? null)->toBeNull()
            ->and($this->extractor->extract('partner brother')['spouse']['first_name'] ?? null)->toBeNull();
    });
});

describe('dependants bucket', function () {
    it('extracts count hint from "two kids"', function () {
        $facts = $this->extractor->extract('two kids');
        expect($facts['dependants']['count_hint'] ?? null)->toBe(2);
    });

    it('extracts numeric count', function () {
        $facts = $this->extractor->extract('3 children');
        expect($facts['dependants']['count_hint'] ?? null)->toBe(3);
    });

    it('extracts people hints from "Sam 8 and Eli 6"', function () {
        $facts = $this->extractor->extract('Sam 8 and Eli 6');
        expect($facts['dependants']['people_hint'] ?? [])->toHaveCount(2)
            ->and($facts['dependants']['people_hint'][0]['name'])->toBe('Sam')
            ->and($facts['dependants']['people_hint'][0]['age_hint'])->toBe(8)
            ->and($facts['dependants']['people_hint'][1]['name'])->toBe('Eli')
            ->and($facts['dependants']['people_hint'][1]['age_hint'])->toBe(6);
    });

    it('extracts people hints from lowercase phrasing', function () {
        $facts = $this->extractor->extract('sam 8 and eli 6');
        expect($facts['dependants']['people_hint'] ?? [])->toHaveCount(2)
            ->and($facts['dependants']['people_hint'][0]['name'])->toBe('Sam')
            ->and($facts['dependants']['people_hint'][1]['name'])->toBe('Eli');
    });

    it('extracts people hints from upper case phrasing', function () {
        $facts = $this->extractor->extract('SAM 8 AND ELI 6');
        expect($facts['dependants']['people_hint'] ?? [])->toHaveCount(2)
            ->and($facts['dependants']['people_hint'][0]['name'])->toBe('Sam')
            ->and($facts['dependants']['people_hint'][1]['name'])->toBe('Eli');
    });

    it('extracts people hints from full sentence with count', function () {
        $facts = $this->extractor->extract('I have two kids, sam 8 and eli 6');
        expect($facts['dependants']['count_hint'] ?? null)->toBe(2)
            ->and($facts['dependants']['people_hint'] ?? [])->toHaveCount(2)
            ->and($facts['dependants']['people_hint'][0]['name'])->toBe('Sam')
            ->and($facts['dependants']['people_hint'][1]['name'])->toBe('Eli');
    });

    it('rejects verb+number false positives', function () {
        // "earning 75 a year" must not produce a person named "Earning".
        $facts = $this->extractor->extract('earning 75 a year, two kids');
        expect($facts['dependants']['people_hint'] ?? null)->toBeNull()
            ->and($facts['dependants']['count_hint'] ?? null)->toBe(2);
    });

    it('rejects relation-noun + number false positives', function () {
        // "kids 2", "son 5", etc. should be rejected even though the regex
        // shape matches — these are context nouns, not names.
        $facts = $this->extractor->extract('kids 2 and child 4');
        expect($facts['dependants']['people_hint'] ?? null)->toBeNull();
    });

    it('rejects "born <year>" type false positives', function () {
        $facts = $this->extractor->extract('born 18 January');
        expect($facts['dependants']['people_hint'] ?? null)->toBeNull();
    });

    it('caps age at 25 for dependants', function () {
        // "Pete 35" is too old for a dependant — drop the entry.
        $facts = $this->extractor->extract('Pete 35');
        expect($facts['dependants']['people_hint'] ?? null)->toBeNull();
    });
});

describe('employment bucket', function () {
    it('extracts employment status', function () {
        $facts = $this->extractor->extract("I'm employed full-time");
        expect($facts['employment']['status'] ?? null)->toBe('full_time');
    });

    it('extracts income when currency signal present', function () {
        $facts = $this->extractor->extract('I earn £75,000 a year');
        expect($facts['employment']['annual_income'] ?? null)->toBe(75000.0);
    });

    it('does not extract income from bare number without currency signal', function () {
        $facts = $this->extractor->extract('aged 42');
        expect($facts['employment']['annual_income'] ?? null)->toBeNull();
    });

    it('extracts employer from capitalised phrasing', function () {
        $facts = $this->extractor->extract('I work for Acme Corp');
        expect($facts['employment']['employer'] ?? null)->toBe('Acme Corp');
    });

    it('extracts employer from lowercase phrasing', function () {
        $facts = $this->extractor->extract('i work for acme');
        expect($facts['employment']['employer'] ?? null)->toBe('Acme');
    });

    it('preserves acronym casing for employer', function () {
        // "HSBC", "BBC", "NHS" — must not be title-cased to "Hsbc"/"Bbc".
        expect($this->extractor->extract('I work for HSBC')['employment']['employer'] ?? null)->toBe('HSBC')
            ->and($this->extractor->extract('I work for NHS')['employment']['employer'] ?? null)->toBe('NHS')
            ->and($this->extractor->extract('working at NatWest')['employment']['employer'] ?? null)->toBe('NatWest');
    });

    it('rejects pronouns and generic places as employer', function () {
        expect($this->extractor->extract('I work for them')['employment']['employer'] ?? null)->toBeNull()
            ->and($this->extractor->extract('I work for myself')['employment']['employer'] ?? null)->toBeNull()
            ->and($this->extractor->extract('I work at home')['employment']['employer'] ?? null)->toBeNull()
            ->and($this->extractor->extract('I work for the')['employment']['employer'] ?? null)->toBeNull();
    });
});

describe('expenditure bucket', function () {
    it('extracts monthly total when phrased explicitly', function () {
        $facts = $this->extractor->extract('I spend about £2,500 per month');
        expect($facts['expenditure']['monthly_total_hint'] ?? null)->toBe(2500.0);
    });
});

describe('parking column integration', function () {
    it('merges extracted facts into the conversation parking column', function () {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $this->extractor->extractAndPark($conversation, "I'm 40, married to Angela, two kids");

        $parked = $conversation->fresh()->onboarding_parked_facts;
        expect($parked['personal']['marital_status'])->toBe('married')
            ->and($parked['personal']['age_hint'])->toBe(40)
            ->and($parked['spouse']['first_name'])->toBe('Angela')
            ->and($parked['dependants']['count_hint'])->toBe(2);
    });

    it('layers incremental extracts without clobbering earlier buckets', function () {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $this->extractor->extractAndPark($conversation, 'married to Angela');
        $this->extractor->extractAndPark($conversation, 'her email is angela@example.com');

        $parked = $conversation->fresh()->onboarding_parked_facts;
        expect($parked['spouse']['first_name'])->toBe('Angela')
            ->and($parked['spouse']['email'])->toBe('angela@example.com');
    });

    it('deduplicates people_hint across multiple extracts', function () {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $this->extractor->extractAndPark($conversation, 'Sam 8 and Eli 6');
        $this->extractor->extractAndPark($conversation, 'Sam 8 is my eldest');

        $people = $conversation->fresh()->onboarding_parked_facts['dependants']['people_hint'];
        expect($people)->toHaveCount(2)
            ->and(array_column($people, 'name'))->toEqualCanonicalizing(['Sam', 'Eli']);
    });

    it('does NOT write to users.* or family_members (parking only)', function () {
        $user = User::factory()->create([
            'marital_status' => null,
            'date_of_birth' => null,
        ]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $this->extractor->extractAndPark($conversation, "I'm 40, married to Angela");

        $fresh = $user->fresh();
        expect($fresh->marital_status)->toBeNull()
            ->and($fresh->date_of_birth)->toBeNull()
            ->and($user->familyMembers()->count())->toBe(0);
    });
});

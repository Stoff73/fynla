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

    it('does not confuse "wife\'s sister" as spouse when name is non-capitalised', function () {
        $facts = $this->extractor->extract('my wife sister angela is coming');
        // "angela" is lowercase so our original-case regex does not match.
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

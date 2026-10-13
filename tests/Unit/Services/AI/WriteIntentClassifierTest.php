<?php

declare(strict_types=1);

use App\Services\AI\WriteIntentClassifier;

beforeEach(function () {
    $this->classifier = new WriteIntentClassifier;
});

describe('WriteIntentClassifier::classify — positive cases (write intents)', function () {
    it('matches imperative add + entity', function () {
        $result = $this->classifier->classify('Add a Cash ISA with Nationwide, balance £5,000');
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('savings_account');
        expect($result['matched_verb'])->toBe('add');
    });

    it('matches "I have" + entity', function () {
        $result = $this->classifier->classify('I have a workplace pension with Aviva');
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('pension');
    });

    it('matches "we bought" + entity', function () {
        $result = $this->classifier->classify('We bought a buy to let in Manchester last year');
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('property');
    });

    it('synthesises a reason from the matched verb and entity keyword', function () {
        $result = $this->classifier->classify('I opened a stocks and shares isa');
        expect($result)->not->toBeNull();
        expect($result['reason'])->toContain('investment_account');
        expect($result['reason'])->toContain('stocks and shares isa');
    });

    it('prefers an explicit goal noun over an incidental property keyword', function () {
        // June13 keyword-precedence bug: "house" is a property keyword listed
        // before goal in ENTITY_KEYWORDS, so this clear goal message used to
        // route to a property capture (Fyn answered about the existing main
        // residence instead of creating the goal). The explicit "goal" noun
        // names the object of the write; "house deposit" only says what the
        // goal is FOR. Goal must win.
        $result = $this->classifier->classify('add a goal to save £25,000 for a house deposit by 2029');
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('goal');
    });

    it('tolerates "also" interposed between the pronoun and the verb (live csjones miss)', function () {
        // Live miss: "i have" is a literal WRITE_VERB_PATTERNS entry, but
        // "i also have" doesn't contain that exact substring, so the verb
        // match silently failed. "savings account" is a substring of
        // "fixed term savings account", so once the verb tolerates the
        // interposed adverb this resolves to savings_account.
        $result = $this->classifier->classify('I also have a Halifax fixed term savings account with £1,500 in it');
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('savings_account');
    });

    it('tolerates "also" interposed after a contraction ("i\'ve also got")', function () {
        $result = $this->classifier->classify("I've also got a Nationwide savings account with £2,000 in it");
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('savings_account');
    });

    it('matches a bare "notice account" entity keyword (live csjones miss)', function () {
        $result = $this->classifier->classify('I have a Santander notice account with £3,000 in it');
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('savings_account');
    });
});

describe('WriteIntentClassifier::classify — F-6 interrogative guard', function () {
    it('rejects messages ending with a question mark', function () {
        // "Should I add to my Cash ISA?" — verb (add) + entity (cash isa)
        // BUT the trailing ? marks it as a question. Pre-F-6 this routed
        // to inline-capture; post-F-6 it returns null and the LLM owns it.
        expect($this->classifier->classify('Should I add to my Cash ISA?'))->toBeNull();
    });

    it('rejects "should i" prefix even without question mark', function () {
        expect($this->classifier->classify('should i open a sipp'))->toBeNull();
    });

    it('rejects "how do i" prefix', function () {
        expect($this->classifier->classify('how do i set up a workplace pension'))->toBeNull();
    });

    it('rejects "what is" prefix', function () {
        expect($this->classifier->classify('what is my savings account interest rate'))->toBeNull();
    });

    it('rejects "can i" prefix', function () {
        expect($this->classifier->classify('Can I add another pension?'))->toBeNull();
    });

    it('rejects "tell me" prefix (not strictly a question)', function () {
        expect($this->classifier->classify('tell me about my cash isa balance'))->toBeNull();
    });

    it('rejects "where should" prefix', function () {
        expect($this->classifier->classify('where should i open a savings account'))->toBeNull();
    });

    it('still accepts a clear imperative even when it contains a question word later', function () {
        // "when" here names the goal's time, it does not ask anything.
        $result = $this->classifier->classify('Add a goal for when I retire');
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('goal');
    });

    it('leaves a question after a colon to the answer process (fix plan T2)', function () {
        // A clause after a colon is read as its own sentence ("Then work it
        // through: …", Brett #4013), so "where should I retire" asks something.
        // CSJ 2026-10-12: a question always goes to the answer process.
        expect($this->classifier->classify('Add a goal: where should I retire'))->toBeNull();
    });
});

describe('WriteIntentClassifier::classify — negative cases', function () {
    it('returns null on empty messages', function () {
        expect($this->classifier->classify(''))->toBeNull();
        expect($this->classifier->classify('   '))->toBeNull();
    });

    it('returns null when no verb matches', function () {
        expect($this->classifier->classify('the cash isa is tax-free'))->toBeNull();
    });

    it('returns null when verb matches but no entity does', function () {
        // "I have an idea" — verb but no entity_type keyword
        expect($this->classifier->classify('i have an idea about my finances'))->toBeNull();
    });

    it('does not over-match on "also" when there is no verb+entity pair', function () {
        // Guards the adverb tolerance above: "i also think" strips down to
        // "i think", which isn't a WRITE_VERB_PATTERNS entry, so this must
        // stay null rather than the adverb-loosening accidentally widening
        // the match.
        expect($this->classifier->classify('I also think I should save more'))->toBeNull();
    });
});

describe('WriteIntentClassifier::classify — genuine amount-parser answers (OnboardingChatDirector parse-guard regression)', function () {
    // OnboardingChatDirector's free_text/value_parser states (e.g.
    // base_expenditure) now consult classify() before accepting a
    // successful parse, so a false positive here would block a genuine
    // amount answer from ever being recorded. None of these three plain
    // amount answers contain a WRITE_VERB_PATTERNS verb, so they must all
    // classify as null — pinned so the parse-guard fix can never regress
    // ordinary onboarding answers.
    it('returns null for "About £1,800 a month"', function () {
        expect($this->classifier->classify('About £1,800 a month'))->toBeNull();
    });

    it('returns null for "£72,000"', function () {
        expect($this->classifier->classify('£72,000'))->toBeNull();
    });

    it('returns null for "roughly 2k"', function () {
        expect($this->classifier->classify('roughly 2k'))->toBeNull();
    });
});

describe('WriteIntentClassifier::proposalAcceptanceIntent — accepting Fyn\'s own offer', function () {
    $offer = "A goal of **£12,000** would give you the full six-month buffer.\n\nWould you like me to help you set up that emergency fund goal now?\n\nFor regulated advice personal to your circumstances, speak to a qualified financial adviser.";

    it('routes a short yes to the goal the offer named', function () use ($offer) {
        $result = $this->classifier->proposalAcceptanceIntent('Yes please, set it up.', $offer);
        expect($result)->not->toBeNull()
            ->and($result['entity_type'])->toBe('goal')
            ->and($result['matched_verb'])->toBe('proposal_accepted');
    });

    it('takes the entity from the offer, not the reply', function () {
        $result = $this->classifier->proposalAcceptanceIntent('Go ahead', 'Shall I add that Cash ISA to your records now?');
        expect($result['entity_type'] ?? null)->toBe('savings_account');
    });

    it('ignores a yes when the last turn made no offer', function () {
        expect($this->classifier->proposalAcceptanceIntent('Yes', 'Your emergency fund covers 1.5 months. Building it up should come first.'))->toBeNull();
    });

    it('ignores a hedged or negative reply', function () use ($offer) {
        expect($this->classifier->proposalAcceptanceIntent('No thanks', $offer))->toBeNull();
        expect($this->classifier->proposalAcceptanceIntent('Yes, but what about my ISA first?', $offer))->toBeNull();
        expect($this->classifier->proposalAcceptanceIntent('Yes, not now though, maybe later', $offer))->toBeNull();
    });

    it('ignores a long reply that is not a bare acceptance', function () use ($offer) {
        $long = 'Yes I think so but I would like to understand how the target is worked out and whether it should include my partner';
        expect($this->classifier->proposalAcceptanceIntent($long, $offer))->toBeNull();
    });
});

// TODO item 7a (walked 2026-10-03): "I want to change my income details" matched
// the verb but no entity, so the turn fell to the model, which wrote "I'll hand
// this over" three times and never did. Income is the work-and-income record:
// the capture side opens its forms (RecordEditForms::sectionForEntityType).
describe('WriteIntentClassifier::classify — income edits reach the income forms', function () {
    it('routes each income phrasing to the work and income record', function (string $message) {
        $result = $this->classifier->classify($message);

        expect($result['entity_type'] ?? null)->toBe('work_details');
    })->with([
        'I want to change my income details',
        'Update my dividend income',
        'Change my dividends to 900 a year',
        'I have trust income of 2000 a year',
        'Correct my interest income',
        'Update my other income',
        'Change my self-employment income',
    ]);

    it('still routes income protection to the protection record', function () {
        expect($this->classifier->classify('Add an income protection policy')['entity_type'] ?? null)->toBe('protection_policy');
    });

    it('still treats a question about income as advice', function () {
        expect($this->classifier->classify('How much income tax will I pay on my dividends?'))->toBeNull();
    });
});

describe('WriteIntentClassifier::classify — a section named in general words', function () {
    // Walked on csjones 2026-10-05 (user 480): "I want to change my savings
    // details" matched no record type, so the model asked in words instead of
    // the edit door opening the records to choose from.
    it('routes a change to a section named in general words', function (string $message, string $entity) {
        expect($this->classifier->classify($message)['entity_type'] ?? null)->toBe($entity);
    })->with([
        ['I want to change my savings details', 'savings_account'],
        ['Update my bank account', 'savings_account'],
        ['I need to change my investments', 'investment_account'],
        ['Please update my protection details', 'protection_policy'],
    ]);

    it('still routes a savings goal to the goal', function () {
        expect($this->classifier->classify('Change my savings goal to £10,000')['entity_type'] ?? null)->toBe('goal');
    });
});

describe('WriteIntentClassifier — a question is never a write (fix plan T2)', function () {
    // Brett's production walk, 2026-10-11 (fix plan section 8): each asked for
    // an answer, and a verb + entity inside the question opened a write.
    it("leaves Brett's questions to the answer process", function (string $message) {
        expect($this->classifier->classify($message))->toBeNull();
    })->with([
        'F22 #3877 (was retirement_goals via "we\'ve got")' => "I'd like to retire at 60, not 67. Dev would carry on teaching until 65. Can we afford it on what we've got, and what would I need to put away each month to make it work? Please use my actual figures.",
        'P1 #3963 (was goal via "i pay" + "saving for")' => 'My Chase savings of £38,000 at 4.5% are in my name and I pay 40% on the interest. Dev earns £48,000 and has no taxable savings interest. Give me the yearly tax saving for three options: gift the whole lot to Dev, make it a joint account, or move £12,000 into my ISA. Show the working for each.',
        'P7 #3973 (was spouse via "i own" + "my husband")' => "If I died tomorrow and everything I own went to my husband Dev, would there be any tax to pay on what I leave? I've been shown a figure of £3,900 on £9,750. Is that right? Explain the working either way.",
        'Q5 #3999 (was property via "record")' => "Rules question, nothing to record. A higher-rate taxpayer gets £8,700 a year as her half of the rent on a jointly owned let flat. Her share of the interest-only mortgage interest is £4,680 a year and of the agent's fee £870. How much tax does she pay on that rent, how is the mortgage interest relieved, and would the household pay less if the other owner, a basic-rate taxpayer, held most of it? Figures please.",
    ]);

    it('does not read a write verb the user negates as a write', function (string $message) {
        expect($this->classifier->classify($message))->toBeNull();
    })->with([
        "Don't record this: the flat brings in £8,700 a year in rent",
        'Nothing to record here, the buy to let is let at £1,450 a month',
        "I didn't ask you to save the pension, it is with Aviva",
    ]);

    it("still writes Brett's genuine additions", function (string $message, string $entity) {
        expect($this->classifier->classify($message)['entity_type'] ?? null)->toBe($entity);
    })->with([
        'M6 #3946' => ['I have £60,000 in an easy access account with Marcus paying 4.3%', 'savings_account'],
        'P8 #3977' => ['Please add my two children: Maya, who is 11, and Arjun, who is 8. They both depend on me and Dev.', 'dependant'],
    ]);

    it('does not take a request for the working as acceptance of an offer', function () {
        $offer = 'Would you like me to help you set up that emergency fund goal now?';

        expect($this->classifier->proposalAcceptanceIntent('Yes please, set it up.', $offer))->not->toBeNull()
            ->and($this->classifier->proposalAcceptanceIntent('Yes, and show the working first', $offer))->toBeNull();
    });
});

describe('WriteIntentClassifier::classify — spouse invitations (T9, Q17)', function () {
    it('files a request to invite the partner as a spouse invitation', function (string $message) {
        expect($this->classifier->classify($message)['entity_type'] ?? null)->toBe('spouse_invite');
    })->with([
        'Q17 statement' => ['Please invite my partner to link accounts. Their email is slaterjoneschris+t1walk@gmail.com.'],
        'named' => ['Invite my wife Angela, angela@example.com'],
        'link up' => ['Link my account with my husband please'],
        'send' => ['Send my spouse an invitation'],
    ]);

    it('leaves a question about inviting to the answer process', function () {
        expect($this->classifier->classify('Please invite my partner. Their email is a@b.com. Is that address written correctly?'))->toBeNull();
    });

    it('keeps a spouse record change a spouse capture', function () {
        expect($this->classifier->classify("Update my wife's salary to £40,000")['entity_type'] ?? null)->not->toBe('spouse_invite');
    });
});

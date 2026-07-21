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
        // "Add a goal" doesn't start with an interrogative prefix and has no `?`.
        $result = $this->classifier->classify('Add a goal: where should I retire');
        // Note: this DOES end without `?` and starts with "add" not an
        // interrogative — so it should match. The classifier's job is to
        // be conservative, not perfect.
        expect($result)->not->toBeNull();
        expect($result['entity_type'])->toBe('goal');
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
});

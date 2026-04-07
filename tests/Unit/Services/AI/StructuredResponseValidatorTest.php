<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Services\AI\StructuredResponseValidator;

beforeEach(function () {
    $this->validator = new StructuredResponseValidator;
});

describe('StructuredResponseValidator', function () {
    describe('acronym detection', function () {
        it('flags IHT acronym', function () {
            $result = $this->validator->validate('Your IHT liability is £50,000.');
            expect($result['valid'])->toBeFalse();
            expect($result['violations'])->toHaveCount(1);
            expect($result['violations'][0]['rule'])->toBe('banned_acronym');
            expect($result['violations'][0]['detail'])->toContain('Inheritance Tax');
        });

        it('flags CGT acronym', function () {
            $result = $this->validator->validate('CGT rates apply here.');
            expect($result['valid'])->toBeFalse();
            expect($result['violations'][0]['detail'])->toContain('Capital Gains Tax');
        });

        it('flags NRB acronym', function () {
            $result = $this->validator->validate('The NRB is £325,000.');
            expect($result['valid'])->toBeFalse();
        });

        it('passes when acronyms are spelled out', function () {
            $result = $this->validator->validate('Your Inheritance Tax liability is £50,000. The Nil Rate Band is £325,000.');
            expect($result['valid'])->toBeTrue();
        });

        it('allows ISA abbreviation', function () {
            $result = $this->validator->validate('Your ISA allowance is £20,000.');
            expect($result['valid'])->toBeTrue();
        });
    });

    describe('record ID detection', function () {
        it('flags exposed record IDs', function () {
            $result = $this->validator->validate('Your savings account ID:375 has £5,000.');
            expect($result['valid'])->toBeFalse();
            expect($result['violations'][0]['rule'])->toBe('exposed_record_id');
        });

        it('flags bracketed IDs', function () {
            $result = $this->validator->validate('Account [ID:42] balance is £10,000.');
            expect($result['valid'])->toBeFalse();
        });

        it('passes when no IDs present', function () {
            $result = $this->validator->validate('Your Barclays savings account has £5,000.');
            expect($result['valid'])->toBeTrue();
        });
    });

    describe('jargon detection', function () {
        it('flags waterfall jargon', function () {
            $result = $this->validator->validate('Using the surplus waterfall approach, we can allocate funds.');
            expect($result['valid'])->toBeFalse();
            expect($result['violations'][0]['rule'])->toBe('banned_jargon');
        });

        it('flags prioritise affordability jargon', function () {
            $result = $this->validator->validate('We should prioritise affordability before investing.');
            expect($result['valid'])->toBeFalse();
        });

        it('passes clean response', function () {
            $result = $this->validator->validate('You may want to consider increasing your pension contributions by £200 per month.');
            expect($result['valid'])->toBeTrue();
        });
    });

    describe('filler phrase detection', function () {
        it('flags response starting with Certainly!', function () {
            $result = $this->validator->validate('Certainly! Let me look at your pension.');
            expect($result['valid'])->toBeFalse();
            expect($result['violations'][0]['rule'])->toBe('filler_phrase');
        });

        it('passes response without filler', function () {
            $result = $this->validator->validate('Looking at your pension position, your current value is £125,000.');
            expect($result['valid'])->toBeTrue();
        });
    });

    describe('missing amounts in advice', function () {
        it('flags advice response with no £ amounts', function () {
            $classification = ['primary' => QuerySchemas::RETIREMENT_CONTRIBUTION, 'related' => [], 'modules' => ['retirement']];
            $result = $this->validator->validate('You should consider increasing your pension contributions.', $classification);
            expect($result['valid'])->toBeFalse();
            expect(collect($result['violations'])->pluck('rule'))->toContain('missing_amounts');
        });

        it('passes advice response with £ amounts', function () {
            $classification = ['primary' => QuerySchemas::RETIREMENT_CONTRIBUTION, 'related' => [], 'modules' => ['retirement']];
            $result = $this->validator->validate('You could contribute an additional £500 per month to your pension.', $classification);
            expect($result['valid'])->toBeTrue();
        });

        it('does not check amounts for data_entry responses', function () {
            $classification = ['primary' => QuerySchemas::DATA_ENTRY, 'related' => [], 'modules' => []];
            $result = $this->validator->validate('I have added your savings account.', $classification);
            expect(collect($result['violations'])->pluck('rule'))->not->toContain('missing_amounts');
        });
    });

    describe('HTML injection detection', function () {
        it('flags script tags', function () {
            $result = $this->validator->validate('Here is your data <script>alert("xss")</script>');
            expect($result['valid'])->toBeFalse();
            expect($result['violations'][0]['rule'])->toBe('html_injection');
            expect($result['violations'][0]['severity'])->toBe('critical');
        });
    });

    describe('context leak detection', function () {
        it('flags leaked context blocks', function () {
            $result = $this->validator->validate('Your net worth is £100,000. [Context: Used get_module_analysis tool]');
            expect($result['valid'])->toBeFalse();
            expect($result['violations'][0]['rule'])->toBe('context_leak');
        });
    });

    describe('sanitise', function () {
        it('strips context blocks', function () {
            $cleaned = $this->validator->sanitise('Your net worth is £100,000. [Context: internal data]');
            expect($cleaned)->toBe('Your net worth is £100,000.');
        });

        it('strips exposed record IDs', function () {
            $cleaned = $this->validator->sanitise('Account ID:375 has £5,000.');
            expect($cleaned)->toBe('Account has £5,000.');
        });

        it('strips script tags', function () {
            $cleaned = $this->validator->sanitise('Hello <script>alert("xss")</script> world');
            expect($cleaned)->toBe('Hello world');
        });

        it('returns clean text unchanged', function () {
            $text = 'Your pension is worth £125,000 and growing well.';
            expect($this->validator->sanitise($text))->toBe($text);
        });
    });
});

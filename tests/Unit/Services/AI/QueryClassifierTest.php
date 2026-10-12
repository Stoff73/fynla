<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Services\AI\QueryClassifier;

beforeEach(function () {
    $this->classifier = new QueryClassifier;
});

describe('QueryClassifier', function () {
    describe('data_entry classification', function () {
        it('classifies "I have a pension with £50,000" as data_entry', function () {
            $result = $this->classifier->classify('I have a pension with £50,000');
            expect($result['primary'])->toBe(QuerySchemas::DATA_ENTRY);
            expect($result['related'])->toBe([]);
        });

        it('classifies "Update my ISA balance to £15,000" as data_entry', function () {
            $result = $this->classifier->classify('Update my ISA balance to £15,000');
            expect($result['primary'])->toBe(QuerySchemas::DATA_ENTRY);
        });

        it('classifies "I earn £75,000" as data_entry', function () {
            $result = $this->classifier->classify('I earn £75,000');
            expect($result['primary'])->toBe(QuerySchemas::DATA_ENTRY);
        });
    });

    describe('navigation classification', function () {
        it('classifies "Take me to estate planning" as navigation', function () {
            $result = $this->classifier->classify('Take me to estate planning');
            expect($result['primary'])->toBe(QuerySchemas::NAVIGATION);
            expect($result['related'])->toBe([]);
        });

        it('classifies "Show me my investments" as navigation', function () {
            $result = $this->classifier->classify('Show me my investments');
            expect($result['primary'])->toBe(QuerySchemas::NAVIGATION);
        });
    });

    describe('billing classification (precedence over navigation)', function () {
        // Regression: billing is answered in-chat via get_subscription_status
        // + list_invoices; <billing_guidance> forbids navigating the user to a
        // settings page. A billing entity in the message must beat the
        // NAVIGATION step so the unified assembler injects <billing_guidance>.
        it('classifies "show me my invoice" as billing, not navigation', function () {
            expect($this->classifier->classify('show me my invoice')['primary'])
                ->toBe(QuerySchemas::BILLING);
        });

        it('classifies "show my subscription" as billing', function () {
            expect($this->classifier->classify('show my subscription')['primary'])
                ->toBe(QuerySchemas::BILLING);
        });

        it('classifies "where is my invoice" as billing', function () {
            expect($this->classifier->classify('where is my invoice')['primary'])
                ->toBe(QuerySchemas::BILLING);
        });

        it('classifies "show me my billing" as billing', function () {
            expect($this->classifier->classify('show me my billing')['primary'])
                ->toBe(QuerySchemas::BILLING);
        });

        it('classifies "what is my subscription status and where are my invoices" as billing', function () {
            expect($this->classifier->classify('what is my subscription status and where are my invoices')['primary'])
                ->toBe(QuerySchemas::BILLING);
        });

        it('classifies an explicit Fynla plan question as billing', function () {
            expect($this->classifier->classify('Which Fynla plan am I on?')['primary'])
                ->toBe(QuerySchemas::BILLING);
        });

        // ISA-subscription is a savings concept, NOT Fynla billing — the
        // fixed-width negative lookbehind must keep it out of BILLING.
        it('does NOT classify "what is my ISA subscription limit" as billing', function () {
            expect($this->classifier->classify('what is my ISA subscription limit')['primary'])
                ->not->toBe(QuerySchemas::BILLING);
        });

        it('does not classify a saved ISA subscription question as billing', function () {
            $result = $this->classifier->classify(
                'Using my saved data, how much of my ISA allowance have I used this tax year, how much remains, and which account contains the subscription?'
            );

            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_ACCOUNTS)
                ->and($result['related'])->toContain(QuerySchemas::TAX_OPTIMISATION)
                ->and($result['related'])->not->toContain(QuerySchemas::BILLING);
        });

        // Genuine navigation with no billing entity must still be navigation.
        it('keeps "take me to my goals page" as navigation', function () {
            expect($this->classifier->classify('take me to my goals page')['primary'])
                ->toBe(QuerySchemas::NAVIGATION);
        });

        it('does not treat a saved tax-plan explanation as subscription billing', function () {
            $result = $this->classifier->classify(
                'Can you explain in plain English why moving £2,612 of my Marcus savings into my ISA could save about £49 a year, using the figures in my plan?'
            );

            expect($result['primary'])->toBe(QuerySchemas::TAX_OPTIMISATION)
                ->and($result['related'])->not->toContain(QuerySchemas::BILLING);
        });
    });

    describe('advice classification', function () {
        it('keeps retirement readiness out of goals-related classification', function () {
            $result = $this->classifier->classify('Am I on track for retirement?');

            expect($result['primary'])->toBe(QuerySchemas::RETIREMENT_READINESS)
                ->and($result['related'])->not->toContain(QuerySchemas::GOALS_PROGRESS);
        });

        it('classifies explicit goal and life event progress as goals', function (string $message) {
            expect($this->classifier->classify($message)['primary'])
                ->toBe(QuerySchemas::GOALS_PROGRESS);
        })->with([
            'Am I on track with my goal?',
            'How is my life event progress?',
        ]);

        it('does not make financial target wording goals-primary without explicit goal language', function (string $message) {
            expect($this->classifier->classify($message)['primary'])
                ->not->toBe(QuerySchemas::GOALS_PROGRESS);
        })->with([
            'Am I on track for my pension target?',
            'Am I on track for my mortgage target?',
            'Am I on track for my house target?',
            'Am I on track for my ISA target?',
            'Am I on track for my savings target?',
            'Am I on track for my investment target?',
        ]);

        it('classifies "How do I maximise my pension?" as retirement_contribution with related types', function () {
            $result = $this->classifier->classify('How do I maximise my pension?');
            expect($result['primary'])->toBe(QuerySchemas::RETIREMENT_CONTRIBUTION);
            expect($result['related'])->toContain(QuerySchemas::TAX_OPTIMISATION);
            expect($result['related'])->toContain(QuerySchemas::AFFORDABILITY);
        });

        it('classifies "Do I have enough life cover?" as protection_cover', function () {
            $result = $this->classifier->classify('Do I have enough life cover?');
            expect($result['primary'])->toBe(QuerySchemas::PROTECTION_COVER);
        });

        it('classifies "What should I do with my bonus?" as holistic_health', function () {
            $result = $this->classifier->classify('What should I do with my bonus?');
            expect($result['primary'])->toBe(QuerySchemas::HOLISTIC_HEALTH);
            expect($result['related'])->toContain(QuerySchemas::SAVINGS_EMERGENCY);
            expect($result['related'])->toContain(QuerySchemas::AFFORDABILITY);
        });

        it('classifies "Should I pay off my mortgage or invest?" as savings_debt with affordability', function () {
            $result = $this->classifier->classify('Should I pay off my mortgage or invest?');
            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_DEBT);
            expect($result['related'])->toContain(QuerySchemas::AFFORDABILITY);
        });

        it('classifies "How is my financial health?" as holistic_health', function () {
            $result = $this->classifier->classify('How is my financial health?');
            expect($result['primary'])->toBe(QuerySchemas::HOLISTIC_HEALTH);
        });

        it('classifies "What is my emergency fund position?" as savings_emergency', function () {
            $result = $this->classifier->classify('Do I have enough cash buffer?');
            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_EMERGENCY);
            expect($result['related'])->toContain(QuerySchemas::AFFORDABILITY);
        });

        // "protected for my savings" is FSCS deposit protection, NOT life
        // insurance. The bare "am i protected" pattern must not claim it.
        it('classifies "am i protected for my savings" as savings_accounts (FSCS, not life cover)', function () {
            $result = $this->classifier->classify('am i protected for my savings');
            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_ACCOUNTS);
        });

        it('classifies "are my savings safe" as savings_accounts (FSCS)', function () {
            $result = $this->classifier->classify('are my savings safe');
            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_ACCOUNTS);
        });

        it('classifies "is my money protected in the bank" as savings_accounts (FSCS)', function () {
            $result = $this->classifier->classify('is my money protected in the bank');
            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_ACCOUNTS);
        });

        it('classifies "is my cash deposit protected" as savings_accounts (FSCS)', function () {
            $result = $this->classifier->classify('is my cash deposit protected');
            expect($result['primary'])->toBe(QuerySchemas::SAVINGS_ACCOUNTS);
        });

        // Regression: genuine life-cover questions must still be protection.
        it('still classifies "am i protected?" (no savings object) as protection_cover', function () {
            $result = $this->classifier->classify('am i protected?');
            expect($result['primary'])->toBe(QuerySchemas::PROTECTION_COVER);
        });

        it('still classifies "am i covered enough" as protection_cover', function () {
            $result = $this->classifier->classify('am i covered enough');
            expect($result['primary'])->toBe(QuerySchemas::PROTECTION_COVER);
        });

        it('still classifies "do I have enough life cover for my family" as protection_cover', function () {
            $result = $this->classifier->classify('do I have enough life cover for my family');
            expect($result['primary'])->toBe(QuerySchemas::PROTECTION_COVER);
        });
    });

    describe('net worth classification', function () {
        it('classifies "What is my net worth?" as holistic_health so financial_context is included', function () {
            $result = $this->classifier->classify('What is my net worth?');
            expect($result['primary'])->toBe(QuerySchemas::HOLISTIC_HEALTH);
        });

        it('classifies "Show me my net worth" as holistic_health (not navigation)', function () {
            $result = $this->classifier->classify('Show me my net worth');
            expect($result['primary'])->toBe(QuerySchemas::HOLISTIC_HEALTH);
        });

        it('classifies "Combined wealth" as holistic_health', function () {
            $result = $this->classifier->classify('Combined wealth');
            expect($result['primary'])->toBe(QuerySchemas::HOLISTIC_HEALTH);
        });
    });

    describe('route-based fallback', function () {
        it('falls back to protection_cover on /protection page', function () {
            $result = $this->classifier->classify('tell me more', '/protection');
            expect($result['primary'])->toBe(QuerySchemas::PROTECTION_COVER);
        });

        it('falls back to general on /dashboard with no keyword match', function () {
            $result = $this->classifier->classify('tell me more', '/dashboard');
            expect($result['primary'])->toBe(QuerySchemas::GENERAL);
        });

        it('falls back to retirement_readiness on /net-worth/retirement page', function () {
            $result = $this->classifier->classify('tell me more', '/net-worth/retirement');
            expect($result['primary'])->toBe(QuerySchemas::RETIREMENT_READINESS);
        });
    });

    describe('module mapping', function () {
        it('includes correct modules for retirement_contribution', function () {
            $result = $this->classifier->classify('How do I maximise my pension?');
            expect($result['modules'])->toContain('retirement');
            expect($result['modules'])->toContain('tax');
            expect($result['modules'])->toContain('savings');
        });

        it('returns empty modules for data_entry', function () {
            $result = $this->classifier->classify('I have a new ISA with £10,000');
            expect($result['modules'])->toBe([]);
        });
    });

    // The savings keyword table is intentionally narrow (saving + account|rate,
    // emergency fund, etc.) so generic "save" phrasings — "save tax", "save for
    // retirement" — are NOT swallowed into a savings classification. A generic
    // "how do I start saving?" therefore stays GENERAL by design; the
    // emergency-fund-first guidance for it is injected by FynContextAssembler,
    // not by reclassifying. These pin that boundary so neither side drifts.
    describe('generic getting-started saving stays general', function () {
        it('classifies "how do I start saving properly?" as general', function () {
            expect($this->classifier->classify('how do I start saving properly?')['primary'])
                ->toBe(QuerySchemas::GENERAL);
        });

        it('classifies "how do I start saving?" as general', function () {
            expect($this->classifier->classify('how do I start saving?')['primary'])
                ->toBe(QuerySchemas::GENERAL);
        });

        it('does not misroute a generic "save tax" question into savings', function () {
            $result = $this->classifier->classify('how can I save tax?');
            expect($result['primary'])->not->toBe(QuerySchemas::SAVINGS_EMERGENCY)
                ->and($result['primary'])->not->toBe(QuerySchemas::SAVINGS_ACCOUNTS);
        });

        it('still classifies a real emergency-fund question as savings_emergency', function () {
            expect($this->classifier->classify('is my emergency fund big enough?')['primary'])
                ->toBe(QuerySchemas::SAVINGS_EMERGENCY);
        });

        it('still classifies a savings-rate question as savings_accounts', function () {
            expect($this->classifier->classify('what savings rate should I be getting?')['primary'])
                ->toBe(QuerySchemas::SAVINGS_ACCOUNTS);
        });
    });
});

/*
 * Brett's production walk of 2026-10-11 (fix plan T3, section 8 replay): the
 * answer process filed these questions as data entry or navigation (bypass
 * types, no tools) or as general, so the prescribed tools never ran.
 */
describe('QueryClassifier — a question is filed under its subject (fix plan T3)', function () {
    it("files each of Brett's questions under the type whose tools carry its figures", function (string $message, string $primary, ?string $route) {
        expect($this->classifier->classify($message, $route)['primary'])->toBe($primary);
    })->with([
        'F19 #3869 (was data_entry)' => ["I've got death in service of 4x salary through Monzo and nothing else. Dev has the Teachers' pension death grant. No income protection, no critical illness. If I died or couldn't work, would the family be ok? What cover would you look at and roughly how much?", QuerySchemas::PROTECTION_COVER, '/protection'],
        'F22 #3877 on /protection (was protection_cover)' => ["I'd like to retire at 60, not 67. Dev would carry on teaching until 65. Can we afford it on what we've got, and what would I need to put away each month to make it work? Please use my actual figures.", QuerySchemas::RETIREMENT_READINESS, '/protection'],
        'F22 #3877 with no page (was general)' => ["I'd like to retire at 60, not 67. Dev would carry on teaching until 65. Can we afford it on what we've got, and what would I need to put away each month to make it work? Please use my actual figures.", QuerySchemas::RETIREMENT_READINESS, null],
        'M8 #3961 (was data_entry)' => ["I have a daughter, she's 27. If I died tomorrow and she inherited everything, how much inheritance tax would there be, and does she get the extra allowance for the house?", QuerySchemas::ESTATE_IHT, null],
        'P3 #3967 (was general)' => ["I didn't ask you to record anything, please don't. It's a hypothetical: how much tax a year is due on £1,710 of interest if it's all mine, if it's all Dev's, and if it's split equally between us? Three numbers with the working.", QuerySchemas::SAVINGS_ACCOUNTS, null],
        'P7 #3973 on /estate (was estate_planning by page)' => ["If I died tomorrow and everything I own went to my husband Dev, would there be any tax to pay on what I leave? I've been shown a figure of £3,900 on £9,750. Is that right? Explain the working either way.", QuerySchemas::ESTATE_IHT, '/estate'],
        'Q5 #3997 (was general)' => ["Now that we let the Woking flat, how is my half of the rent taxed this year? Can the mortgage interest and the agent's fee be deducted, and would the household pay less if the flat were held mainly by the lower earner? Figures please.", QuerySchemas::PROPERTY, null],
        'Q5 #3999' => ["Rules question, nothing to record. A higher-rate taxpayer gets £8,700 a year as her half of the rent on a jointly owned let flat. Her share of the interest-only mortgage interest is £4,680 a year and of the agent's fee £870. How much tax does she pay on that rent, how is the mortgage interest relieved, and would the household pay less if the other owner, a basic-rate taxpayer, held most of it? Figures please.", QuerySchemas::PROPERTY, null],
        'Q6 #4003 (was navigation)' => ["If I stop work at 60 and want £70,000 a year after that, how far short am I on what's on file, and what would I need to put away each month from now to close it? Show the working.", QuerySchemas::RETIREMENT_READINESS, '/net-worth/retirement'],
        'Q7 #4009 (was navigation)' => ['Pure arithmetic, nothing to record. Projected £62,894 a year at 60 against a wanted £70,373 a year. What is the gap per year and per month, what extra pot does the gap need at a 4% withdrawal rate, and what monthly saving over 19 years at 5% a year growth builds that extra pot? Show the working.', QuerySchemas::RETIREMENT_READINESS, null],
        'Q7 #4013' => ['Then work it through: projected £62,894 a year at 60 against a wanted £70,373. Gap per year and per month, the extra pot that gap needs at a 4% withdrawal rate, and the monthly saving over 19 years at 5% growth that builds it. Show the working.', QuerySchemas::RETIREMENT_READINESS, null],
        'Q8 #4015 (was tax_optimisation)' => ["Rules and numbers only, nothing to record. On what's on file, if both of us died next year and everything went to the kids, what would the death tax bill be, and what are the three best things to do about it? Figures please.", QuerySchemas::ESTATE_IHT, null],
        'Q10 #4033 (was general)' => ['Hang on, I told you earlier the Chase account was £38,000 but I think I may have said £83,000 at some point. Which figure do you have on file, and which should it be?', QuerySchemas::SAVINGS_ACCOUNTS, null],
    ]);

    it('brings the pension tools to a pension what-if asked with net worth (P5 #3971)', function () {
        $result = $this->classifier->classify("Run a what-if for me: if I paid an extra £20,000 a year into my Aviva pot from now until 60, what would the pot and my net worth be at 60 compared with today's projection?");

        expect(QuerySchemas::getRequiredToolsForClassification($result))->toContain('get_module_analysis(retirement)');
    });

    it('gives a retirement question the retirement projection tool (Q6)', function () {
        $result = $this->classifier->classify("If I stop work at 60 and want £70,000 a year after that, how far short am I on what's on file, and what would I need to put away each month from now to close it? Show the working.");

        expect(QuerySchemas::getRequiredToolsForClassification($result))->toContain('get_module_analysis(retirement)');
    });

    it('never files a request for the working as navigation', function () {
        expect($this->classifier->classify('Show the working for each')['primary'])->not->toBe(QuerySchemas::NAVIGATION);
    });

    it('keeps the new wording to its own subject', function (string $message, string $primary) {
        expect($this->classifier->classify($message)['primary'])->toBe($primary);
    })->with([
        'emergency fund shortfall' => ['How far short is my emergency fund of six months?', QuerySchemas::SAVINGS_EMERGENCY],
        'protection shortfall' => ['Is there a protection gap if I died or could not work?', QuerySchemas::PROTECTION_COVER],
        'savings interest rate' => ['What savings rate should I be getting on my interest?', QuerySchemas::SAVINGS_ACCOUNTS],
    ]);

    it('leaves a goal\'s monthly saving out of retirement', function () {
        expect($this->classifier->classify('What should I put away each month to reach my house deposit goal?')['primary'])
            ->not->toBe(QuerySchemas::RETIREMENT_READINESS);
    });

    it('still files plain statements and commands as before', function (string $message, string $primary) {
        expect($this->classifier->classify($message)['primary'])->toBe($primary);
    })->with([
        ['I have a pension with £50,000', QuerySchemas::DATA_ENTRY],
        ['Take me to estate planning', QuerySchemas::NAVIGATION],
        ['Show me my investments', QuerySchemas::NAVIGATION],
    ]);
});

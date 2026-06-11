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

        // ISA-subscription is a savings concept, NOT Fynla billing — the
        // fixed-width negative lookbehind must keep it out of BILLING.
        it('does NOT classify "what is my ISA subscription limit" as billing', function () {
            expect($this->classifier->classify('what is my ISA subscription limit')['primary'])
                ->not->toBe(QuerySchemas::BILLING);
        });

        // Genuine navigation with no billing entity must still be navigation.
        it('keeps "take me to my goals page" as navigation', function () {
            expect($this->classifier->classify('take me to my goals page')['primary'])
                ->toBe(QuerySchemas::NAVIGATION);
        });
    });

    describe('advice classification', function () {
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

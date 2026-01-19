<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ConsentService::class);
    $this->user = User::factory()->create();
});

describe('recordConsent', function () {
    it('records consent for a type', function () {
        $consent = $this->service->recordConsent($this->user, 'terms', true);

        expect($consent)->toBeInstanceOf(UserConsent::class);
        expect(UserConsent::where('user_id', $this->user->id)
            ->where('consent_type', 'terms')
            ->exists())->toBeTrue();
    });

    it('records consent as true by default', function () {
        $consent = $this->service->recordConsent($this->user, 'privacy');

        expect($consent->consented)->toBeTrue();
    });

    it('can record declined consent', function () {
        $consent = $this->service->recordConsent($this->user, 'marketing', false);

        expect($consent->consented)->toBeFalse();
    });
});

describe('recordConsents', function () {
    it('records multiple consents at once', function () {
        $consents = $this->service->recordConsents($this->user, [
            'terms' => true,
            'privacy' => true,
            'marketing' => false,
        ]);

        expect($consents)->toHaveCount(3);
        expect($consents['terms']->consented)->toBeTrue();
        expect($consents['privacy']->consented)->toBeTrue();
        expect($consents['marketing']->consented)->toBeFalse();
    });
});

describe('hasConsent', function () {
    it('returns true when user has consented', function () {
        $this->service->recordConsent($this->user, 'terms', true);

        expect($this->service->hasConsent($this->user, 'terms'))->toBeTrue();
    });

    it('returns false when user has not consented', function () {
        $this->service->recordConsent($this->user, 'marketing', false);

        expect($this->service->hasConsent($this->user, 'marketing'))->toBeFalse();
    });

    it('returns false when no consent record exists', function () {
        expect($this->service->hasConsent($this->user, 'nonexistent'))->toBeFalse();
    });
});

describe('getUserConsents', function () {
    it('returns all current consents for user', function () {
        $this->service->recordConsent($this->user, 'terms', true);
        $this->service->recordConsent($this->user, 'privacy', true);
        $this->service->recordConsent($this->user, 'marketing', false);

        $consents = $this->service->getUserConsents($this->user);

        expect($consents)->toBeArray();
        expect($consents)->toHaveKey('terms');
        expect($consents)->toHaveKey('privacy');
        expect($consents)->toHaveKey('marketing');
    });

    it('returns empty array for user with no consents', function () {
        $consents = $this->service->getUserConsents($this->user);

        expect($consents)->toBeArray();
    });
});

describe('withdrawConsent', function () {
    it('withdraws existing consent', function () {
        $this->service->recordConsent($this->user, 'marketing', true);
        expect($this->service->hasConsent($this->user, 'marketing'))->toBeTrue();

        $this->service->withdrawConsent($this->user, 'marketing');

        // After withdrawal, should not have consent
        $consent = UserConsent::where('user_id', $this->user->id)
            ->where('consent_type', 'marketing')
            ->first();

        expect($consent->withdrawn_at)->not->toBeNull();
    });
});

describe('hasRequiredConsents', function () {
    it('returns false when missing required consents', function () {
        expect($this->service->hasRequiredConsents($this->user))->toBeFalse();
    });

    it('returns true when all required consents are given', function () {
        $this->service->recordConsent($this->user, UserConsent::TYPE_TERMS, true);
        $this->service->recordConsent($this->user, UserConsent::TYPE_PRIVACY, true);
        $this->service->recordConsent($this->user, UserConsent::TYPE_DATA_PROCESSING, true);

        expect($this->service->hasRequiredConsents($this->user))->toBeTrue();
    });
});

describe('getConsentHistory', function () {
    it('returns all consent records for user', function () {
        $this->service->recordConsent($this->user, 'terms', true);
        $this->service->recordConsent($this->user, 'privacy', true);

        $history = $this->service->getConsentHistory($this->user);

        expect($history)->toHaveCount(2);
    });
});

<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\MFAService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(MFAService::class);
    $this->user = User::factory()->create([
        'mfa_enabled' => false,
        'mfa_secret' => null,
        'mfa_recovery_codes' => null,
    ]);
});

describe('generateSecret', function () {
    it('generates a secret key', function () {
        $secret = $this->service->generateSecret();

        expect($secret)->toBeString()->not->toBeEmpty();
        expect(strlen($secret))->toBeGreaterThanOrEqual(16);
    });

    it('generates unique secrets each time', function () {
        $secret1 = $this->service->generateSecret();
        $secret2 = $this->service->generateSecret();

        expect($secret1)->not->toBe($secret2);
    });
});

describe('getQRCodeDataUri', function () {
    it('returns a data URI for QR code', function () {
        $secret = $this->service->generateSecret();
        $dataUri = $this->service->getQRCodeDataUri($this->user, $secret);

        expect($dataUri)->toStartWith('data:image/svg+xml;base64,');
    });
});

describe('enableMFA', function () {
    it('enables MFA for user', function () {
        $secret = $this->service->generateSecret();
        $recoveryCodes = $this->service->enableMFA($this->user, $secret);

        $this->user->refresh();
        expect($this->user->mfa_enabled)->toBeTrue();
        expect($this->user->mfa_secret)->not->toBeNull();
        expect($this->user->mfa_confirmed_at)->not->toBeNull();
    });

    it('returns 10 recovery codes', function () {
        $secret = $this->service->generateSecret();
        $recoveryCodes = $this->service->enableMFA($this->user, $secret);

        expect($recoveryCodes)->toHaveCount(10);
        expect($recoveryCodes[0])->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/');
    });

    it('stores encrypted secret', function () {
        $secret = $this->service->generateSecret();
        $this->service->enableMFA($this->user, $secret);

        $this->user->refresh();
        // Secret should be encrypted
        expect($this->user->mfa_secret)->not->toBe($secret);
        // Should be able to decrypt it back
        expect(Crypt::decryptString($this->user->mfa_secret))->toBe($secret);
    });

    it('stores hashed recovery codes', function () {
        $secret = $this->service->generateSecret();
        $recoveryCodes = $this->service->enableMFA($this->user, $secret);

        $this->user->refresh();
        $storedCodes = $this->user->mfa_recovery_codes;

        expect($storedCodes)->toHaveCount(10);
        // Codes should be hashed, not plain text
        expect($storedCodes[0])->not->toBe($recoveryCodes[0]);
    });
});

describe('disableMFA', function () {
    it('disables MFA for user', function () {
        // First enable MFA
        $secret = $this->service->generateSecret();
        $this->service->enableMFA($this->user, $secret);

        // Then disable it
        $this->service->disableMFA($this->user);

        $this->user->refresh();
        expect($this->user->mfa_enabled)->toBeFalse();
        expect($this->user->mfa_secret)->toBeNull();
        expect($this->user->mfa_recovery_codes)->toBeNull();
    });
});

describe('verifyRecoveryCode', function () {
    it('returns true for valid recovery code', function () {
        $secret = $this->service->generateSecret();
        $recoveryCodes = $this->service->enableMFA($this->user, $secret);

        $result = $this->service->verifyRecoveryCode($this->user, $recoveryCodes[0]);

        expect($result)->toBeTrue();
    });

    it('removes used recovery code', function () {
        $secret = $this->service->generateSecret();
        $recoveryCodes = $this->service->enableMFA($this->user, $secret);

        $this->service->verifyRecoveryCode($this->user, $recoveryCodes[0]);

        $this->user->refresh();
        expect($this->user->mfa_recovery_codes)->toHaveCount(9);
    });

    it('returns false for invalid recovery code', function () {
        $secret = $this->service->generateSecret();
        $this->service->enableMFA($this->user, $secret);

        $result = $this->service->verifyRecoveryCode($this->user, 'WRONG-CODE-HERE');

        expect($result)->toBeFalse();
    });

    it('returns false when no recovery codes exist', function () {
        $result = $this->service->verifyRecoveryCode($this->user, 'SOME-CODE');

        expect($result)->toBeFalse();
    });
});

describe('regenerateRecoveryCodes', function () {
    it('generates 10 new recovery codes', function () {
        $secret = $this->service->generateSecret();
        $this->service->enableMFA($this->user, $secret);

        $newCodes = $this->service->regenerateRecoveryCodes($this->user);

        expect($newCodes)->toHaveCount(10);
    });

    it('replaces old recovery codes', function () {
        $secret = $this->service->generateSecret();
        $originalCodes = $this->service->enableMFA($this->user, $secret);

        $newCodes = $this->service->regenerateRecoveryCodes($this->user);

        // New codes should be different
        expect($newCodes)->not->toEqual($originalCodes);

        // Old codes should no longer work
        $result = $this->service->verifyRecoveryCode($this->user, $originalCodes[0]);
        expect($result)->toBeFalse();
    });
});

describe('getRemainingRecoveryCodeCount', function () {
    it('returns 0 when no codes exist', function () {
        $count = $this->service->getRemainingRecoveryCodeCount($this->user);

        expect($count)->toBe(0);
    });

    it('returns 10 after enabling MFA', function () {
        $secret = $this->service->generateSecret();
        $this->service->enableMFA($this->user, $secret);

        $count = $this->service->getRemainingRecoveryCodeCount($this->user);

        expect($count)->toBe(10);
    });

    it('returns 9 after using one code', function () {
        $secret = $this->service->generateSecret();
        $codes = $this->service->enableMFA($this->user, $secret);
        $this->service->verifyRecoveryCode($this->user, $codes[0]);

        $count = $this->service->getRemainingRecoveryCodeCount($this->user);

        expect($count)->toBe(9);
    });
});

describe('hasMFAEnabled', function () {
    it('returns false when MFA is not enabled', function () {
        expect($this->service->hasMFAEnabled($this->user))->toBeFalse();
    });

    it('returns true when MFA is enabled', function () {
        $secret = $this->service->generateSecret();
        $this->service->enableMFA($this->user, $secret);

        expect($this->service->hasMFAEnabled($this->user))->toBeTrue();
    });

    it('returns false after MFA is disabled', function () {
        $secret = $this->service->generateSecret();
        $this->service->enableMFA($this->user, $secret);
        $this->service->disableMFA($this->user);

        expect($this->service->hasMFAEnabled($this->user))->toBeFalse();
    });
});

describe('generateRecoveryCodes', function () {
    it('generates specified number of codes', function () {
        $codes5 = $this->service->generateRecoveryCodes(5);
        $codes15 = $this->service->generateRecoveryCodes(15);

        expect($codes5)->toHaveCount(5);
        expect($codes15)->toHaveCount(15);
    });

    it('generates codes in correct format', function () {
        $codes = $this->service->generateRecoveryCodes();

        foreach ($codes as $code) {
            expect($code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/');
        }
    });
});

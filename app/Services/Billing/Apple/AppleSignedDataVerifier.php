<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Data\Billing\Apple\VerifiedAppleTransaction;

interface AppleSignedDataVerifier
{
    public function verifyTransaction(
        string $jws,
        string $expectedEnvironment,
        ?string $expectedAppAccountToken,
    ): VerifiedAppleTransaction;

    public function verifyNotification(
        string $jws,
        string $expectedEnvironment,
    ): VerifiedAppleNotification;
}

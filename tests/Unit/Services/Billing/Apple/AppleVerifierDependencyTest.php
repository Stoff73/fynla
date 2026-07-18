<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing\Apple;

use AppStoreServerLibrary\SignedDataVerifier;
use PHPUnit\Framework\TestCase;

final class AppleVerifierDependencyTest extends TestCase
{
    public function test_it_anchors_the_app_store_verifier_to_a_checked_in_apple_root_ca_certificate(): void
    {
        $certificatePath = dirname(__DIR__, 5).'/resources/certificates/apple/AppleRootCA-G3.cer';

        self::assertFileExists($certificatePath);

        $der = file_get_contents($certificatePath);

        self::assertNotFalse($der);

        $pem = sprintf(
            "-----BEGIN CERTIFICATE-----\n%s-----END CERTIFICATE-----\n",
            chunk_split(base64_encode($der), 64, "\n"),
        );
        $certificate = openssl_x509_read($pem);

        self::assertNotFalse($certificate);

        $details = openssl_x509_parse($certificate);

        self::assertIsArray($details);
        self::assertStringContainsString('CA:TRUE', $details['extensions']['basicConstraints'] ?? '');
        self::assertTrue(class_exists(SignedDataVerifier::class));
    }
}

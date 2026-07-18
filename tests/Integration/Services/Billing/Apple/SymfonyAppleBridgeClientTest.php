<?php

declare(strict_types=1);

use App\Exceptions\Billing\AppleVerificationException;
use App\Services\Billing\Apple\SymfonyAppleBridgeClient;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->captured = [];
    $captured = &$this->captured;
    $this->factory = static function (
        array $command,
        string $workingDirectory,
        array $environment,
        string $input,
        float $timeout,
    ) use (&$captured): Process {
        $captured = compact('command', 'workingDirectory', 'environment', 'input', 'timeout');

        return new Process($command, $workingDirectory, $environment, $input, $timeout);
    };
});

it('uses exact argv, puts signed data only on stdin and strips inherited secrets', function () {
    putenv('DATABASE_URL=private-database-secret');
    $_SERVER['DATABASE_URL'] = 'private-database-secret';

    try {
        $client = appleTestBridgeClient($this->factory);
        $result = $client->call('health', [
            'scenario' => 'success',
            'signed_data' => 'private.signed.value',
        ]);
    } finally {
        putenv('DATABASE_URL');
        unset($_SERVER['DATABASE_URL']);
    }

    $decodedInput = json_decode($this->captured['input'], true, flags: JSON_THROW_ON_ERROR);

    expect($this->captured['command'])->toBe([
        PHP_BINARY,
        base_path('tests/fixtures/apple_bridge_stub.php'),
    ])
        ->and($this->captured['workingDirectory'])->toBe(base_path())
        ->and($this->captured['timeout'])->toBe(2.0)
        ->and($this->captured['environment']['DATABASE_URL'])->toBeFalse()
        ->and($this->captured['input'])->toContain('private.signed.value')
        ->and(implode(' ', $this->captured['command']))->not->toContain('private.signed.value')
        ->and($decodedInput)->toMatchArray([
            'version' => 1,
            'operation' => 'health',
            'scenario' => 'success',
            'signed_data' => 'private.signed.value',
        ])
        ->and($result['contains_signed_value'])->toBeTrue()
        ->and($result['argv'])->toBe([base_path('tests/fixtures/apple_bridge_stub.php')])
        ->and($result['environment_keys'])->not->toContain('DATABASE_URL')
        ->and($result['stdin_sha256'])->toBe(hash('sha256', $this->captured['input']));
});

it('maps a valid nonzero failure envelope to its stable error only', function () {
    $client = appleTestBridgeClient($this->factory);

    try {
        $client->call('verify_transaction', [
            'scenario' => 'failure',
            'signed_data' => 'private.signed.value',
        ]);
        test()->fail('Expected the bridge failure to throw.');
    } catch (AppleVerificationException $exception) {
        expect($exception->errorCode)->toBe('invalid_signed_data')
            ->and($exception->retryable)->toBeFalse()
            ->and($exception->getMessage())->toBe('invalid_signed_data')
            ->and($exception->getMessage())->not->toContain('private.signed.value')
            ->and($exception->getPrevious())->toBeNull();
    }
});

it('maps process timeout to retryable unavailable without process detail', function () {
    $client = appleTestBridgeClient($this->factory, timeout: 0.05);

    try {
        $client->call('health', [
            'scenario' => 'timeout',
            'signed_data' => 'private.signed.value',
        ]);
        test()->fail('Expected timeout to throw.');
    } catch (AppleVerificationException $exception) {
        expect($exception->errorCode)->toBe('verifier_unavailable')
            ->and($exception->retryable)->toBeTrue()
            ->and($exception->getMessage())->not->toContain('private.signed.value')
            ->and($exception->getPrevious())->toBeNull();
    }
});

it('fails closed on malformed, contradictory or noisy process output', function (string $scenario) {
    $client = appleTestBridgeClient($this->factory, maxResponseBytes: 1024);

    try {
        $client->call('health', [
            'scenario' => $scenario,
            'signed_data' => 'private.signed.value',
        ]);
        test()->fail('Expected invalid process output to throw.');
    } catch (AppleVerificationException $exception) {
        expect($exception->errorCode)->toBe('verifier_unavailable')
            ->and($exception->retryable)->toBeTrue()
            ->and($exception->getMessage())->toBe('verifier_unavailable')
            ->and($exception->getMessage())->not->toContain('private.signed.value', 'sensitive source detail')
            ->and($exception->getPrevious())->toBeNull();
    }
})->with([
    'malformed',
    'oversized',
    'multiple',
    'stderr',
    'wrong_version',
    'extra_envelope_field',
    'no_newline',
    'success_nonzero',
    'failure_zero',
    'bad_failure_shape',
    'list_data',
]);

it('rejects oversized or reserved request payloads before launching a process', function (array $payload) {
    $launched = false;
    $factory = static function () use (&$launched): Process {
        $launched = true;
        throw new RuntimeException('must not launch');
    };
    $client = appleTestBridgeClient($factory, maxRequestBytes: 128);

    expect(fn () => $client->call('health', $payload))
        ->toThrow(AppleVerificationException::class, 'invalid_configuration')
        ->and($launched)->toBeFalse();
})->with([
    'oversized' => [['signed_data' => str_repeat('x', 256)]],
    'reserved version' => [['version' => 2]],
    'reserved operation' => [['operation' => 'verify_transaction']],
    'numeric top-level key' => [[0 => 'unexpected']],
]);

it('rejects relative executable or cli paths at construction', function (string $python, string $cli) {
    expect(fn () => new SymfonyAppleBridgeClient(
        pythonExecutable: $python,
        cliPath: $cli,
    ))->toThrow(AppleVerificationException::class, 'invalid_configuration');
})->with([
    ['python3.12', dirname(__DIR__, 4).'/fixtures/apple_bridge_stub.php'],
    [PHP_BINARY, 'tests/fixtures/apple_bridge_stub.php'],
]);

function appleTestBridgeClient(
    Closure $factory,
    float $timeout = 2.0,
    int $maxRequestBytes = 4096,
    int $maxResponseBytes = 4096,
): SymfonyAppleBridgeClient {
    return new SymfonyAppleBridgeClient(
        pythonExecutable: PHP_BINARY,
        cliPath: base_path('tests/fixtures/apple_bridge_stub.php'),
        timeout: $timeout,
        maxRequestBytes: $maxRequestBytes,
        maxResponseBytes: $maxResponseBytes,
        processFactory: $factory,
        workingDirectory: base_path(),
    );
}

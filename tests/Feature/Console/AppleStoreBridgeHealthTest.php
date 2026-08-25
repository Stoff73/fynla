<?php

declare(strict_types=1);

use App\Services\Billing\Apple\AppleBridgeClient;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config()->set('apple_store.python_executable', PHP_BINARY);
    config()->set(
        'apple_store.bridge_cli_path',
        base_path('services/apple_store_bridge/cli.py'),
    );
    config()->set(
        'apple_store.root_certificate_path',
        base_path('resources/certificates/apple/AppleRootCA-G3.cer'),
    );
});

it('reports the exact safe JSON when the locked bridge is healthy', function (): void {
    bindAppleBridgeHealthResult([
        'service' => 'apple-store-bridge',
        'contract' => 1,
        'library' => '3.1.2',
    ]);

    $this->artisan('apple-store:bridge-health --json')
        ->expectsOutput('{"success":true,"python":true,"bridge":true,"contract":1,"library":"3.1.2","root_certificate":true}')
        ->assertExitCode(0);
});

it('fails safely when the configured Python runtime is missing', function (): void {
    config()->set('apple_store.python_executable', '/secret/missing/python');
    bindAppleBridgeHealthResult([
        'service' => 'apple-store-bridge',
        'contract' => 1,
        'library' => '3.1.2',
    ]);

    expect(Artisan::call('apple-store:bridge-health', ['--json' => true]))
        ->toBe(1)
        ->and(trim(Artisan::output()))
        ->toBe('{"success":false,"python":false,"bridge":false,"contract":null,"library":null,"root_certificate":true}')
        ->not->toContain('secret', 'missing', 'python');
});

it('fails safely when the bridge times out', function (): void {
    bindAppleBridgeHealthFailure(
        new RuntimeException('timeout at /secret/runtime with signed.payload'),
    );

    expect(Artisan::call('apple-store:bridge-health', ['--json' => true]))
        ->toBe(1)
        ->and(trim(Artisan::output()))
        ->toBe('{"success":false,"python":true,"bridge":false,"contract":null,"library":null,"root_certificate":true}')
        ->not->toContain('timeout', 'secret', 'signed.payload');
});

it('fails closed on the wrong bridge contract version', function (): void {
    bindAppleBridgeHealthResult([
        'service' => 'apple-store-bridge',
        'contract' => 2,
        'library' => '3.1.2',
    ]);

    expect(Artisan::call('apple-store:bridge-health', ['--json' => true]))
        ->toBe(1)
        ->and(trim(Artisan::output()))
        ->toBe('{"success":false,"python":true,"bridge":true,"contract":2,"library":"3.1.2","root_certificate":true}');
});

it('fails closed on the wrong Apple library version', function (): void {
    bindAppleBridgeHealthResult([
        'service' => 'apple-store-bridge',
        'contract' => 1,
        'library' => '3.1.1',
    ]);

    expect(Artisan::call('apple-store:bridge-health', ['--json' => true]))
        ->toBe(1)
        ->and(trim(Artisan::output()))
        ->toBe('{"success":false,"python":true,"bridge":true,"contract":1,"library":"3.1.1","root_certificate":true}');
});

function bindAppleBridgeHealthResult(array $result): void
{
    app()->instance(AppleBridgeClient::class, new class($result) implements AppleBridgeClient
    {
        public function __construct(private readonly array $result) {}

        public function call(string $operation, array $payload): array
        {
            if ($operation !== 'health' || $payload !== []) {
                throw new RuntimeException('Unexpected bridge call.');
            }

            return $this->result;
        }
    });
}

function bindAppleBridgeHealthFailure(Throwable $failure): void
{
    app()->instance(AppleBridgeClient::class, new class($failure) implements AppleBridgeClient
    {
        public function __construct(private readonly Throwable $failure) {}

        public function call(string $operation, array $payload): array
        {
            throw $this->failure;
        }
    });
}

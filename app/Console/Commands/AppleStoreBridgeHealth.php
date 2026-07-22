<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\Apple\AppleBridgeClient;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

final class AppleStoreBridgeHealth extends Command
{
    private const CONTRACT_VERSION = 1;

    private const LIBRARY_VERSION = '3.1.2';

    protected $signature = 'apple-store:bridge-health {--json}';

    protected $description = 'Check the local Apple verifier bridge without exposing configuration';

    public function handle(): int
    {
        $python = $this->validExecutable(
            config('apple_store.python_executable'),
        );
        $cli = $this->validReadableFile(
            config('apple_store.bridge_cli_path'),
        );
        $rootCertificate = $this->validReadableFile(
            config('apple_store.root_certificate_path'),
        );

        $bridge = false;
        $contract = null;
        $library = null;

        if ($python && $cli && $rootCertificate) {
            try {
                $health = app(AppleBridgeClient::class)->call('health', []);
                $service = $health['service'] ?? null;
                $reportedContract = $health['contract'] ?? null;
                $reportedLibrary = $health['library'] ?? null;

                if (
                    $service === 'apple-store-bridge'
                    && is_int($reportedContract)
                    && is_string($reportedLibrary)
                ) {
                    $bridge = true;
                    $contract = $reportedContract;
                    $library = $reportedLibrary;
                }
            } catch (Throwable) {
                // Health output is deliberately limited to safe booleans and versions.
            }
        }

        $result = [
            'success' => $python
                && $bridge
                && $contract === self::CONTRACT_VERSION
                && $library === self::LIBRARY_VERSION
                && $rootCertificate,
            'python' => $python,
            'bridge' => $bridge,
            'contract' => $contract,
            'library' => $library,
            'root_certificate' => $rootCertificate,
        ];

        $this->renderResult($result);

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }

    private function validExecutable(mixed $path): bool
    {
        return is_string($path)
            && str_starts_with($path, DIRECTORY_SEPARATOR)
            && is_file($path)
            && is_executable($path);
    }

    private function validReadableFile(mixed $path): bool
    {
        return is_string($path)
            && str_starts_with($path, DIRECTORY_SEPARATOR)
            && is_file($path)
            && is_readable($path);
    }

    /** @param array<string, bool|int|string|null> $result */
    private function renderResult(array $result): void
    {
        if ((bool) $this->option('json')) {
            try {
                $this->line(json_encode(
                    $result,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                ));
            } catch (JsonException) {
                $this->line('{"success":false,"python":false,"bridge":false,"contract":null,"library":null,"root_certificate":false}');
            }

            return;
        }

        $this->table(
            ['Check', 'Result'],
            collect($result)->map(
                static fn (bool|int|string|null $value, string $key): array => [
                    $key,
                    match (true) {
                        $value === true => 'yes',
                        $value === false => 'no',
                        $value === null => 'unavailable',
                        default => (string) $value,
                    },
                ],
            )->values()->all(),
        );
    }
}

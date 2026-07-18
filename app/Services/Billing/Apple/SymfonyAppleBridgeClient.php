<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Exceptions\Billing\AppleVerificationException;
use Closure;
use JsonException;
use Symfony\Component\Process\Process;
use Throwable;

final class SymfonyAppleBridgeClient implements AppleBridgeClient
{
    private const CONTRACT_VERSION = 1;

    private const OPERATIONS = [
        'health',
        'verify_transaction',
        'verify_notification',
        'verify_renewal',
        'reconcile',
    ];

    private readonly Closure $processFactory;

    private readonly string $workingDirectory;

    public function __construct(
        private readonly string $pythonExecutable,
        private readonly string $cliPath,
        private readonly float $timeout = 40.0,
        private readonly int $maxRequestBytes = 262_144,
        private readonly int $maxResponseBytes = 1_048_576,
        ?Closure $processFactory = null,
        ?string $workingDirectory = null,
    ) {
        if (
            trim($this->pythonExecutable) === ''
            || trim($this->cliPath) === ''
            || ! str_starts_with($this->pythonExecutable, DIRECTORY_SEPARATOR)
            || ! str_starts_with($this->cliPath, DIRECTORY_SEPARATOR)
            || ! is_file($this->pythonExecutable)
            || ! is_executable($this->pythonExecutable)
            || ! is_file($this->cliPath)
            || ! is_readable($this->cliPath)
            || $this->timeout <= 0
            || $this->timeout > 60
            || $this->maxRequestBytes <= 0
            || $this->maxResponseBytes <= 0
        ) {
            throw new AppleVerificationException('invalid_configuration');
        }

        $this->workingDirectory = $workingDirectory ?? base_path();
        if (! is_dir($this->workingDirectory)) {
            throw new AppleVerificationException('invalid_configuration');
        }

        $this->processFactory = $processFactory ?? static fn (
            array $command,
            string $cwd,
            array $environment,
            string $input,
            float $timeout,
        ): Process => new Process($command, $cwd, $environment, $input, $timeout);
    }

    public function call(string $operation, array $payload): array
    {
        if (
            ! in_array($operation, self::OPERATIONS, true)
            || array_key_exists('version', $payload)
            || array_key_exists('operation', $payload)
            || count(array_filter(
                array_keys($payload),
                static fn (mixed $key): bool => is_string($key),
            )) !== count($payload)
        ) {
            throw new AppleVerificationException('invalid_configuration');
        }

        try {
            $input = json_encode(
                [
                    'version' => self::CONTRACT_VERSION,
                    'operation' => $operation,
                    ...$payload,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                64,
            );
        } catch (JsonException) {
            throw new AppleVerificationException('invalid_configuration');
        }

        if (strlen($input) > $this->maxRequestBytes) {
            throw new AppleVerificationException('invalid_configuration');
        }

        try {
            $process = ($this->processFactory)(
                [$this->pythonExecutable, $this->cliPath],
                $this->workingDirectory,
                $this->minimalEnvironment(),
                $input,
                $this->timeout,
            );
            if (! $process instanceof Process) {
                throw new AppleVerificationException(
                    'verifier_unavailable',
                    retryable: true,
                );
            }

            $exitCode = $process->run();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
        } catch (Throwable) {
            throw new AppleVerificationException(
                'verifier_unavailable',
                retryable: true,
            );
        }

        return $this->validatedResponse($stdout, $stderr, $exitCode);
    }

    /** @return array<string, mixed> */
    private function validatedResponse(
        string $stdout,
        string $stderr,
        int $exitCode,
    ): array {
        if (
            $stdout === ''
            || strlen($stdout) > $this->maxResponseBytes
            || ! str_ends_with($stdout, "\n")
            || substr_count($stdout, "\n") !== 1
        ) {
            $this->throwUnavailable();
        }

        try {
            $objectEnvelope = json_decode(
                $stdout,
                false,
                64,
                JSON_THROW_ON_ERROR,
            );
            $envelope = json_decode(
                $stdout,
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            $this->throwUnavailable();
        }

        if (
            ! is_array($envelope)
            || array_is_list($envelope)
            || ! ($objectEnvelope instanceof \stdClass)
            || ($envelope['version'] ?? null) !== self::CONTRACT_VERSION
            || ! array_key_exists('ok', $envelope)
            || ! is_bool($envelope['ok'])
        ) {
            $this->throwUnavailable();
        }

        if ($envelope['ok'] === true) {
            if (
                $exitCode !== 0
                || $stderr !== ''
                || ! $this->hasExactKeys($envelope, ['version', 'ok', 'data'])
                || ! is_array($envelope['data'])
                || ! (($objectEnvelope->data ?? null) instanceof \stdClass)
            ) {
                $this->throwUnavailable();
            }

            return $envelope['data'];
        }

        if (
            $exitCode !== 2
            || ! $this->hasExactKeys($envelope, ['version', 'ok', 'error'])
            || ! is_array($envelope['error'])
            || ! (($objectEnvelope->error ?? null) instanceof \stdClass)
            || ! $this->hasExactKeys($envelope['error'], ['code', 'retryable'])
        ) {
            $this->throwUnavailable();
        }

        $code = $envelope['error']['code'] ?? null;
        $retryable = $envelope['error']['retryable'] ?? null;
        if (
            ! is_string($code)
            || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $code) !== 1
            || ! is_bool($retryable)
            || ($stderr !== '' && $stderr !== "{$code}\n")
        ) {
            $this->throwUnavailable();
        }

        throw new AppleVerificationException($code, $retryable);
    }

    /** @param array<string, mixed> $value */
    private function hasExactKeys(array $value, array $expectedKeys): bool
    {
        $actualKeys = array_keys($value);
        sort($actualKeys);
        sort($expectedKeys);

        return $actualKeys === $expectedKeys;
    }

    /** @return array<string, string|false> */
    private function minimalEnvironment(): array
    {
        $inherited = getenv();
        $keys = is_array($inherited) ? array_keys($inherited) : [];
        $keys = array_merge($keys, array_keys($_ENV), array_keys($_SERVER));
        $keys = array_values(array_unique(array_filter(
            $keys,
            static fn (mixed $key): bool => is_string($key) && $key !== '',
        )));

        $environment = array_fill_keys($keys, false);
        $environment['PATH'] = dirname($this->pythonExecutable).':/usr/bin:/bin';
        $environment['LANG'] = 'C';
        $environment['LC_ALL'] = 'C';
        $environment['PYTHONDONTWRITEBYTECODE'] = '1';
        $environment['PYTHONUNBUFFERED'] = '1';

        return $environment;
    }

    private function throwUnavailable(): never
    {
        throw new AppleVerificationException(
            'verifier_unavailable',
            retryable: true,
        );
    }
}

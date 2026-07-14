<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use InvalidArgumentException;
use JsonException;

class RegistrationHandoffService
{
    private const TOKEN_VERSION = 1;

    private const TOKEN_LIFETIME_MINUTES = 15;

    private const KINDS = ['verification', 'restoration'];

    public function __construct(
        private readonly Encrypter $encrypter,
    ) {}

    public function issue(PendingRegistration|User $subject, string $kind, string $source): string
    {
        $this->assertKind($kind);
        $this->assertSource($source);

        if (($kind === 'verification') !== ($subject instanceof PendingRegistration)) {
            throw new InvalidArgumentException('The registration handoff purpose does not match its subject.');
        }

        return $this->encrypter->encryptString(json_encode([
            'version' => self::TOKEN_VERSION,
            'kind' => $kind,
            'source' => $source,
            'subject_id' => $subject->getKey(),
            'subject_fingerprint' => $this->subjectFingerprint($subject),
            'expires_at' => now()->addMinutes(self::TOKEN_LIFETIME_MINUTES)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{kind: 'verification'|'restoration', source: string, pending_id?: int, masked_email?: string, first_name?: string, deleted_at?: string}
     */
    public function resolve(string $token): array
    {
        $payload = $this->decode($token);

        if ($payload['kind'] === 'verification') {
            $pending = PendingRegistration::query()->find($payload['subject_id']);
            if (! $pending || $pending->isExpired()) {
                throw new InvalidArgumentException('This registration link is no longer valid. Please start again.');
            }
            $this->assertSubjectFingerprint($pending, $payload['subject_fingerprint']);

            return [
                'kind' => 'verification',
                'source' => $payload['source'],
                'pending_id' => $pending->id,
                'masked_email' => $this->maskEmail($pending->email),
                'first_name' => $pending->first_name,
            ];
        }

        $user = $this->findRestorationSubject($payload['subject_id']);
        $this->assertSubjectFingerprint($user, $payload['subject_fingerprint']);

        return [
            'kind' => 'restoration',
            'source' => $payload['source'],
        ];
    }

    public function restorationSubject(string $token): User
    {
        return $this->restorationContext($token)['user'];
    }

    /**
     * @return array{user: User, source: string}
     */
    public function restorationContext(string $token): array
    {
        $payload = $this->decode($token, 'restoration');
        $user = $this->findRestorationSubject($payload['subject_id']);
        $this->assertSubjectFingerprint($user, $payload['subject_fingerprint']);

        return ['user' => $user, 'source' => $payload['source']];
    }

    /**
     * @return array{version: int, kind: 'verification'|'restoration', source: string, subject_id: int, subject_fingerprint: string, expires_at: int}
     */
    private function decode(string $token, ?string $expectedKind = null): array
    {
        try {
            $payload = json_decode($this->encrypter->decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new InvalidArgumentException('This registration link is invalid. Please start again.');
        }

        if (! is_array($payload)
            || ($payload['version'] ?? null) !== self::TOKEN_VERSION
            || ! is_string($payload['kind'] ?? null)
            || ! is_string($payload['source'] ?? null)
            || ! is_int($payload['subject_id'] ?? null)
            || ! is_string($payload['subject_fingerprint'] ?? null)
            || strlen($payload['subject_fingerprint']) !== 64
            || ! is_int($payload['expires_at'] ?? null)) {
            throw new InvalidArgumentException('This registration link is invalid. Please start again.');
        }

        $this->assertKind($payload['kind']);
        $this->assertSource($payload['source']);

        if ($expectedKind !== null && $payload['kind'] !== $expectedKind) {
            throw new InvalidArgumentException('This registration link cannot be used for that action.');
        }

        if ($payload['expires_at'] < now()->getTimestamp()) {
            throw new InvalidArgumentException('This registration link has expired. Please start again.');
        }

        return $payload;
    }

    private function findRestorationSubject(int $subjectId): User
    {
        $user = User::withTrashed()->find($subjectId);
        if (! $user || ! $user->canBeRestored()) {
            throw new InvalidArgumentException('This registration link is no longer valid. Please start again.');
        }

        return $user;
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException('Unsupported registration handoff purpose.');
        }
    }

    private function assertSource(string $source): void
    {
        if (! array_key_exists($source, (array) config('onboarding.campaign_map', []))) {
            throw new InvalidArgumentException('Unsupported campaign source.');
        }
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visibleStart = mb_substr($name, 0, 1);
        $visibleEnd = mb_strlen($name) > 2 ? mb_substr($name, -1) : '';

        return $visibleStart.str_repeat('*', max(3, mb_strlen($name) - 2)).$visibleEnd.'@'.$domain;
    }

    private function subjectFingerprint(PendingRegistration|User $subject): string
    {
        $lifecycle = $subject instanceof PendingRegistration
            ? (string) $subject->verification_code
            : (string) $subject->deleted_at?->format('U.u');

        return hash('sha256', $subject::class.'|'.$subject->password.'|'.$lifecycle);
    }

    private function assertSubjectFingerprint(PendingRegistration|User $subject, string $fingerprint): void
    {
        if (! hash_equals($this->subjectFingerprint($subject), $fingerprint)) {
            throw new InvalidArgumentException('This registration link is no longer valid. Please start again.');
        }
    }
}

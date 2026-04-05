<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    use Auditable;

    public const TYPE_TERMS = 'terms';

    public const TYPE_PRIVACY = 'privacy';

    public const TYPE_MARKETING = 'marketing';

    public const TYPE_DATA_PROCESSING = 'data_processing';

    // Current versions of each consent type
    public const CURRENT_VERSIONS = [
        self::TYPE_TERMS => 'v1.0',
        self::TYPE_PRIVACY => 'v1.0',
        self::TYPE_MARKETING => 'v1.0',
        self::TYPE_DATA_PROCESSING => 'v1.0',
    ];

    protected $fillable = [
        'user_id',
        'consent_type',
        'version',
        'consented',
        'consented_at',
        'withdrawn_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'consented' => 'boolean',
        'consented_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record consent for a user
     */
    public static function recordConsent(
        int $userId,
        string $consentType,
        bool $consented = true,
        ?string $version = null
    ): self {
        $version = $version ?? self::CURRENT_VERSIONS[$consentType] ?? 'v1.0';

        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'consent_type' => $consentType,
                'version' => $version,
            ],
            [
                'consented' => $consented,
                'consented_at' => $consented ? now() : null,
                'withdrawn_at' => $consented ? null : now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]
        );
    }

    /**
     * Withdraw consent
     */
    public function withdraw(): void
    {
        $this->update([
            'consented' => false,
            'withdrawn_at' => now(),
        ]);
    }

    /**
     * Check if user has given consent for a type
     */
    public static function hasConsent(int $userId, string $consentType, ?string $version = null): bool
    {
        $version = $version ?? self::CURRENT_VERSIONS[$consentType] ?? 'v1.0';

        return self::where('user_id', $userId)
            ->where('consent_type', $consentType)
            ->where('version', $version)
            ->where('consented', true)
            ->exists();
    }

    /**
     * Get all current consents for a user
     */
    public static function getUserConsents(int $userId): array
    {
        $consents = [];

        foreach (self::CURRENT_VERSIONS as $type => $version) {
            $consent = self::where('user_id', $userId)
                ->where('consent_type', $type)
                ->where('version', $version)
                ->first();

            $consents[$type] = [
                'consented' => $consent?->consented ?? false,
                'version' => $version,
                'consented_at' => $consent?->consented_at?->toIso8601String(),
            ];
        }

        return $consents;
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('consent_type', $type);
    }
}

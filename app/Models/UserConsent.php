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

    public const TYPE_AI_CHAT = 'ai_chat';

    /**
     * Cookie-banner consent, recorded as two types because the banner covers
     * two materially different activities: measuring how the site is used, and
     * attributing an affiliate referral. They are enforced separately already —
     * analytics by the tag loader, affiliate by CaptureAwcCookie — and a single
     * `cookies` row could not say which of them the visitor had agreed to.
     *
     * Both are written from the ONE click, in one call, by
     * App\Services\Consent\CookieConsentService and nowhere else. Two rows for
     * one user action is not two write paths.
     *
     * Unlike every other type these are given before an account exists, so
     * their rows may be keyed to a subject_token instead of a user_id.
     */
    public const TYPE_COOKIES_ANALYTICS = 'cookies_analytics';

    public const TYPE_COOKIES_AFFILIATE = 'cookies_affiliate';

    /**
     * The types the cookie banner writes. One home for the list, so a consumer
     * cannot come to know about one of them and not the other.
     *
     * @var array<int, string>
     */
    public const COOKIE_BANNER_TYPES = [
        self::TYPE_COOKIES_ANALYTICS,
        self::TYPE_COOKIES_AFFILIATE,
    ];

    // Current versions of each consent type
    public const CURRENT_VERSIONS = [
        self::TYPE_TERMS => 'v1.0',
        self::TYPE_PRIVACY => 'v1.0',
        self::TYPE_MARKETING => 'v1.0',
        self::TYPE_DATA_PROCESSING => 'v1.0',
        self::TYPE_AI_CHAT => 'v1.0',
        self::TYPE_COOKIES_ANALYTICS => 'v1.0',
        self::TYPE_COOKIES_AFFILIATE => 'v1.0',
    ];

    protected $fillable = [
        'user_id',
        'subject_token',
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
     * Record consent for an anonymous visitor, identified by an opaque
     * per-browser subject token rather than a user id.
     *
     * Same shape, same versioning and the same withdrawal semantics as
     * recordConsent() — the only difference is who the subject is. Used for
     * cookie-banner consent, which is given before any account exists.
     */
    public static function recordAnonymousConsent(
        string $subjectToken,
        string $consentType,
        bool $consented = true,
        ?string $version = null
    ): self {
        $version = $version ?? self::CURRENT_VERSIONS[$consentType] ?? 'v1.0';

        return self::updateOrCreate(
            [
                'subject_token' => $subjectToken,
                'consent_type' => $consentType,
                'version' => $version,
            ],
            [
                'user_id' => null,
                'consented' => $consented,
                'consented_at' => $consented ? now() : null,
                'withdrawn_at' => $consented ? null : now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]
        );
    }

    /**
     * Attach a visitor's anonymous consent rows to the account they have just
     * created, so a consent given before sign-up appears in that user's
     * consent history.
     *
     * A row is left unclaimed when the user already holds a consent of the
     * same type and version: the (user_id, consent_type, version) unique key
     * forbids a duplicate, and overwriting or deleting either record would
     * destroy evidence. Returns the number of rows claimed.
     */
    public static function claimAnonymousConsents(string $subjectToken, int $userId): int
    {
        $claimed = 0;

        $rows = self::query()
            ->where('subject_token', $subjectToken)
            ->whereNull('user_id')
            ->get();

        foreach ($rows as $row) {
            $alreadyHeld = self::query()
                ->where('user_id', $userId)
                ->where('consent_type', $row->consent_type)
                ->where('version', $row->version)
                ->exists();

            if ($alreadyHeld) {
                continue;
            }

            $row->update(['user_id' => $userId, 'subject_token' => null]);
            $claimed++;
        }

        return $claimed;
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

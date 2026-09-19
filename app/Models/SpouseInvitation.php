<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation to plan together, sent to an address with no account yet.
 * The token on the email link is what registration consumes.
 */
class SpouseInvitation extends Model
{
    use HasFactory;

    public const VALID_DAYS = 30;

    protected $fillable = [
        'inviter_id', 'email', 'first_name', 'token', 'expires_at', 'accepted_at', 'accepted_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /** Unexpired and not yet used. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }
}

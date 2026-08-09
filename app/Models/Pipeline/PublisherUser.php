<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whitelist of users allowed to push articles to the live environment.
 * Admin manages via /admin/pipeline/publishers.
 */
class PublisherUser extends Model
{
    protected $table = 'pipeline_publisher_users';

    protected $guarded = ['id'];

    protected $casts = [
        'granted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public static function isPublisher(int $userId): bool
    {
        return static::where('user_id', $userId)->exists();
    }
}

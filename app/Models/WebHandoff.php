<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebHandoffDestination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebHandoff extends Model
{
    use MassPrunable;

    protected $guarded = ['id'];

    protected $casts = [
        'destination' => WebHandoffDestination::class,
        'expires_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<WebHandoff> */
    public function prunable(): Builder
    {
        $retentionCutoff = now()->subDay();

        return self::query()
            ->where(function (Builder $query) use ($retentionCutoff): void {
                $query->whereNotNull('consumed_at')
                    ->where('consumed_at', '<=', $retentionCutoff);
            })
            ->orWhere(function (Builder $query) use ($retentionCutoff): void {
                $query->whereNull('consumed_at')
                    ->where('expires_at', '<=', $retentionCutoff);
            });
    }
}

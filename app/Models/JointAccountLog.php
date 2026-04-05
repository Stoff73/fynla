<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JointAccountLog extends Model
{
    use HasFactory, HasJointOwnership;

    protected $fillable = [
        'user_id',
        'joint_owner_id',
        'loggable_type',
        'loggable_id',
        'changes',
        'action',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    /**
     * Get the user who made the edit.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the joint owner affected by the edit.
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Get the loggable model (Property, Mortgage, InvestmentAccount, SavingsAccount).
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a log entry for a joint account edit.
     */
    public static function logEdit(
        int $userId,
        int $jointOwnerId,
        Model $loggable,
        array $changes,
        string $action = 'update'
    ): self {
        return self::create([
            'user_id' => $userId,
            'joint_owner_id' => $jointOwnerId,
            'loggable_type' => get_class($loggable),
            'loggable_id' => $loggable->id,
            'changes' => $changes,
            'action' => $action,
        ]);
    }
}

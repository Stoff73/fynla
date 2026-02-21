<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @deprecated Use App\Models\Goal instead. This legacy savings goal model
 * is retained for read-only compatibility. New goals should be created
 * via the Goals module.
 */
class SavingsGoal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'goal_name',
        'target_amount',
        'current_saved',
        'target_date',
        'priority',
        'linked_account_id',
        'auto_transfer_amount',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_saved' => 'decimal:2',
        'target_date' => 'date',
        'auto_transfer_amount' => 'decimal:2',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Linked savings account relationship
     */
    public function linkedAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'linked_account_id');
    }
}

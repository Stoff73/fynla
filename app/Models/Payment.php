<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $hidden = [
        'revolut_payment_data',
        'revolut_order_id',
    ];

    protected $fillable = [
        'subscription_id',
        'user_id',
        'revolut_order_id',
        'amount',
        'currency',
        'status',
        'description',
        'revolut_payment_data',
    ];

    protected $casts = [
        'amount' => 'integer',
        'revolut_payment_data' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

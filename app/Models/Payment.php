<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'subscription_id',
        'user_id',
        'revolut_order_id',
        'amount',
        'currency',
        'status',
        'revolut_payment_data',
        'description',
        'plan_slug',
        'billing_cycle',
        'upgrade_from_plan',
        'discount_code_id',
        'discount_amount',
        'invoice_id',
        'revolut_subscription_payment',
        'awin_order_ref',
        'awin_cks',
        'awin_customer_acquisition',
        'awin_fired_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'revolut_payment_data' => 'array',
        'discount_amount' => 'integer',
        'revolut_subscription_payment' => 'boolean',
        'awin_fired_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

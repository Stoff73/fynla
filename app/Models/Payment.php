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
        'active_checkout_user_id',
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
        'financial_finalized_at',
        'confirmation_email_claimed_at',
        'confirmation_email_sent_at',
        'reconciliation_misses',
        'last_reconciled_at',
        'revolut_subscription_payment',
        'awin_order_ref',
        'awin_cks',
        'awin_customer_acquisition',
        'awin_claimed_at',
        'awin_fired_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'revolut_payment_data' => 'array',
        'discount_amount' => 'integer',
        'revolut_subscription_payment' => 'boolean',
        'financial_finalized_at' => 'datetime',
        'confirmation_email_claimed_at' => 'datetime',
        'confirmation_email_sent_at' => 'datetime',
        'reconciliation_misses' => 'integer',
        'last_reconciled_at' => 'datetime',
        'awin_claimed_at' => 'datetime',
        'awin_fired_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            $payment->active_checkout_user_id = $payment->status === 'pending'
                ? $payment->user_id
                : null;
        });

        static::deleting(function (Payment $payment): void {
            if (! $payment->isForceDeleting() && $payment->status === 'pending') {
                $payment->forceFill([
                    'status' => 'failed',
                    'active_checkout_user_id' => null,
                ])->saveQuietly();
            }
        });
    }

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

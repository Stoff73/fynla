<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'payment_id',
        'subscription_id',
        'invoice_number',
        'status',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'currency',
        'discount_code',
        'discount_description',
        'plan_name',
        'billing_cycle',
        'period_start',
        'period_end',
        'next_renewal_date',
        'issued_at',
        'pdf_path',
        'billing_name',
        'billing_address',
        'billing_email',
    ];

    protected $casts = [
        'subtotal_amount' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'next_renewal_date' => 'date',
        'issued_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Generate the next sequential invoice number atomically.
     * Uses lockForUpdate() on invoice_sequences table for gap-free numbering.
     */
    public static function generateNumber(): string
    {
        return DB::transaction(function () {
            $sequence = DB::table('invoice_sequences')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $nextValue = $sequence->next_value;

            DB::table('invoice_sequences')
                ->where('id', 1)
                ->update(['next_value' => $nextValue + 1]);

            return 'FYN-INV-' . str_pad((string) $nextValue, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Get the total amount formatted in pounds.
     */
    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_amount / 100, 2);
    }

    /**
     * Get the subtotal formatted in pounds.
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return number_format($this->subtotal_amount / 100, 2);
    }

    /**
     * Get the discount formatted in pounds.
     */
    public function getFormattedDiscountAttribute(): string
    {
        return number_format($this->discount_amount / 100, 2);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function markVoid(): void
    {
        $this->update(['status' => 'void']);
    }
}

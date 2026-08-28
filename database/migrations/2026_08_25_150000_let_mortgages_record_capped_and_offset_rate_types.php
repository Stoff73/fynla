<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W-0328 — capped and offset are real UK mortgage products the column could not hold.
 *
 * CSJ decided on 2026-08-25 that both should be supported. The scope is deliberately
 * "record the type", not "model the arithmetic", because **every input an offset would
 * affect is already user-entered and already correct.**
 *
 * `monthly_payment` is supplied by the user (`StoreMortgageRequest:63`), stored as
 * given (`MortgageService:62`) and read back as stored (`:202`). `calculateMonthlyPayment()`
 * never touches it — it backs a standalone what-if endpoint that takes its inputs from
 * the request. So an offset borrower enters the payment their lender actually charges,
 * which already has the offset in it, and the same is true of the balance and of a
 * linked savings account's rate. Deriving the offset ourselves would put a second
 * mechanism against a figure the user already stated, which is the disease W-0228 was
 * ruled to end.
 *
 * `rate_type` drives no arithmetic for any of its five existing values either — the
 * API resource gates which rate fields it sends, the detail view prints it, nothing
 * calculates from it. Two more descriptive values are consistent with that, not a
 * departure from it.
 */
return new class extends Migration
{
    private const WIDENED = "ENUM('fixed', 'variable', 'tracker', 'discount', 'mixed', 'capped', 'offset')";

    private const ORIGINAL = "ENUM('fixed', 'variable', 'tracker', 'discount', 'mixed')";

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE mortgages MODIFY COLUMN rate_type '.self::WIDENED." NOT NULL DEFAULT 'fixed'"
        );
    }

    /**
     * Narrowing fails if any row holds one of the new values, which is correct — a
     * rollback must not silently rewrite a user's stated product as something else.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE mortgages MODIFY COLUMN rate_type '.self::ORIGINAL." NOT NULL DEFAULT 'fixed'"
        );
    }
};

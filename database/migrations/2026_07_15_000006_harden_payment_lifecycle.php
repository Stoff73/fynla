<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')
            ->where('revolut_order_id', 'pending')
            ->orderBy('id')
            ->get(['id'])
            ->each(fn (object $payment) => DB::table('payments')
                ->where('id', $payment->id)
                ->update(['revolut_order_id' => 'pending_'.$payment->id]));
        DB::table('payments')
            ->where('status', 'pending')
            ->whereNotNull('deleted_at')
            ->update(['status' => 'failed']);

        $this->assertNoDuplicates('payments', 'revolut_order_id');
        $this->assertNoDuplicates('invoices', 'payment_id');
        $this->assertNoDuplicates('discount_code_usages', 'payment_id');
        $this->assertNoDuplicates('referrals', 'referee_id');
        $this->assertOnePendingCheckoutPerUser();

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_checkout_user_id')->nullable()->after('user_id');
            $table->unsignedTinyInteger('reconciliation_misses')->default(0)->after('revolut_payment_data');
            $table->timestamp('last_reconciled_at')->nullable()->after('reconciliation_misses');
            $table->timestamp('financial_finalized_at')->nullable()->after('invoice_id');
            $table->timestamp('confirmation_email_claimed_at')->nullable()->after('financial_finalized_at');
            $table->timestamp('confirmation_email_sent_at')->nullable()->after('confirmation_email_claimed_at');
            $table->timestamp('awin_claimed_at')->nullable()->after('awin_fired_at');
            $table->unique('revolut_order_id', 'payments_revolut_order_id_unique');
        });
        DB::table('payments')
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->update(['active_checkout_user_id' => DB::raw('user_id')]);
        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('active_checkout_user_id', 'payments_active_checkout_user_id_unique');
            $table->foreign('active_checkout_user_id', 'payments_active_checkout_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique('payment_id', 'invoices_payment_id_unique');
        });
        Schema::table('discount_code_usages', function (Blueprint $table): void {
            $table->unique('payment_id', 'discount_code_usages_payment_id_unique');
        });
        Schema::table('referrals', function (Blueprint $table): void {
            $table->unique('referee_id', 'referrals_referee_id_unique');
        });

        // Payments completed before this durable finalization pipeline cannot
        // safely replay customer email, referral, invoice, or affiliate side
        // effects during a later reconciliation sweep.
        DB::table('payments')->where('status', 'completed')->update([
            'financial_finalized_at' => DB::raw('COALESCE(financial_finalized_at, updated_at)'),
            'confirmation_email_sent_at' => DB::raw('COALESCE(confirmation_email_sent_at, updated_at)'),
            'awin_fired_at' => DB::raw('COALESCE(awin_fired_at, updated_at)'),
        ]);

        DB::statement('ALTER TABLE subscriptions MODIFY auto_renew TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropUnique('referrals_referee_id_unique');
        });
        Schema::table('discount_code_usages', function (Blueprint $table): void {
            $table->dropUnique('discount_code_usages_payment_id_unique');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_payment_id_unique');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign('payments_active_checkout_user_id_foreign');
            $table->dropUnique('payments_active_checkout_user_id_unique');
            $table->dropUnique('payments_revolut_order_id_unique');
            $table->dropColumn([
                'active_checkout_user_id',
                'reconciliation_misses',
                'last_reconciled_at',
                'financial_finalized_at',
                'confirmation_email_claimed_at',
                'confirmation_email_sent_at',
                'awin_claimed_at',
            ]);
        });

        DB::statement('ALTER TABLE subscriptions MODIFY auto_renew TINYINT(1) NOT NULL DEFAULT 1');
    }

    private function assertNoDuplicates(string $table, string $column): void
    {
        $duplicates = DB::table($table)
            ->whereNotNull($column)
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException(
                "Cannot harden {$table}.{$column}: {$duplicates} duplicate value group(s) require reconciliation."
            );
        }
    }

    private function assertOnePendingCheckoutPerUser(): void
    {
        $duplicates = DB::table('payments')
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException(
                "Cannot harden active checkout slots: {$duplicates} user(s) have multiple pending payments."
            );
        }
    }
};

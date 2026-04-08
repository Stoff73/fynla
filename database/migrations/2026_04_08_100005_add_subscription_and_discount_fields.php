<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'discount_code_id')) {
                $table->foreignId('discount_code_id')->nullable()->after('upgrade_from_plan')
                    ->constrained('discount_codes')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'discount_amount')) {
                $table->integer('discount_amount')->default(0)->after('discount_code_id');
            }
            if (! Schema::hasColumn('payments', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('discount_amount')
                    ->constrained('invoices')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'revolut_subscription_payment')) {
                $table->boolean('revolut_subscription_payment')->default(false)->after('invoice_id');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'revolut_plan_id')) {
                $table->string('revolut_plan_id')->nullable()->after('revolut_subscription_id');
            }
            if (! Schema::hasColumn('subscriptions', 'revolut_plan_variation_id')) {
                $table->string('revolut_plan_variation_id')->nullable()->after('revolut_plan_id');
            }
            if (! Schema::hasColumn('subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(true)->after('revolut_plan_variation_id');
            }
            if (! Schema::hasColumn('subscriptions', 'payment_method_saved')) {
                $table->boolean('payment_method_saved')->default(false)->after('auto_renew');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn(['discount_amount', 'revolut_subscription_payment']);
            $table->dropConstrainedForeignId('invoice_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['revolut_plan_id', 'revolut_plan_variation_id', 'auto_renew', 'payment_method_saved']);
        });
    }
};

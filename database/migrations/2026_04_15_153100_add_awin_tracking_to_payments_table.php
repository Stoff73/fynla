<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'awin_order_ref')) {
                $table->string('awin_order_ref')->nullable()->after('revolut_payment_data');
                $table->index('awin_order_ref');
            }

            if (! Schema::hasColumn('payments', 'awin_cks')) {
                $table->string('awin_cks', 255)->nullable()->after('awin_order_ref');
            }

            if (! Schema::hasColumn('payments', 'awin_customer_acquisition')) {
                $table->enum('awin_customer_acquisition', ['new', 'existing'])->nullable()->after('awin_cks');
            }

            if (! Schema::hasColumn('payments', 'awin_fired_at')) {
                $table->timestamp('awin_fired_at')->nullable()->after('awin_customer_acquisition');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'awin_order_ref')) {
                $table->dropIndex(['awin_order_ref']);
                $table->dropColumn('awin_order_ref');
            }
            if (Schema::hasColumn('payments', 'awin_cks')) {
                $table->dropColumn('awin_cks');
            }
            if (Schema::hasColumn('payments', 'awin_customer_acquisition')) {
                $table->dropColumn('awin_customer_acquisition');
            }
            if (Schema::hasColumn('payments', 'awin_fired_at')) {
                $table->dropColumn('awin_fired_at');
            }
        });
    }
};

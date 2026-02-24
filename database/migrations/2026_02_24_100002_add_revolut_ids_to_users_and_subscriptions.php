<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('revolut_customer_id')->nullable()->after('trial_ends_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('revolut_subscription_id')->nullable()->after('revolut_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('revolut_customer_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('revolut_subscription_id');
        });
    }
};

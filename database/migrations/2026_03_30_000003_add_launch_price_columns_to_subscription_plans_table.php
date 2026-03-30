<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('launch_monthly_price')->nullable()->after('yearly_price');
            $table->integer('launch_yearly_price')->nullable()->after('launch_monthly_price');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['launch_monthly_price', 'launch_yearly_price']);
        });
    }
};

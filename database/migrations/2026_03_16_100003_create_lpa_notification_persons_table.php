<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lpa_notification_persons')) {
            return;
        }

        Schema::create('lpa_notification_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lasting_power_of_attorney_id')
                ->constrained('lasting_powers_of_attorney')
                ->cascadeOnDelete();
            $table->string('full_name');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_county')->nullable();
            $table->string('address_postcode', 10)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('lasting_power_of_attorney_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpa_notification_persons');
    }
};

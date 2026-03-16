<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lpa_attorneys')) {
            return;
        }

        Schema::create('lpa_attorneys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lasting_power_of_attorney_id')
                ->constrained('lasting_powers_of_attorney')
                ->cascadeOnDelete();
            $table->enum('attorney_type', ['primary', 'replacement']);
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_county')->nullable();
            $table->string('address_postcode', 10)->nullable();
            $table->string('relationship_to_donor')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['lasting_power_of_attorney_id', 'attorney_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpa_attorneys');
    }
};

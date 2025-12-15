<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_configuration_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_configuration_id')->constrained('tax_configurations')->onDelete('cascade');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('change_type'); // 'created', 'updated', 'activated', 'deactivated', 'duplicated'
            $table->json('before_state')->nullable(); // Previous config_data (null for 'created')
            $table->json('after_state'); // New config_data
            $table->json('changed_fields')->nullable(); // List of fields that changed (for quick reference)
            $table->text('rationale')->nullable(); // Admin's reason for the change
            $table->string('ip_address', 45)->nullable(); // For compliance
            $table->timestamps();

            $table->index('tax_configuration_id');
            $table->index('changed_by_user_id');
            $table->index('change_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_configuration_audits');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_configurations', function (Blueprint $table) {
            $table->id();
            $table->enum('tier', ['free', 'tier1', 'tier2', 'tier3'])->unique();
            $table->string('display_name');
            $table->unsignedInteger('price_monthly_pence')->default(0);
            $table->unsignedInteger('price_annual_pence')->default(0);
            $table->string('revolut_plan_variation_id')->nullable();
            $table->json('capability_matrix');   // entity_key => full|none|limited|teaser
            $table->json('count_caps');          // entity_key => int|null (null = unlimited)
            $table->unsignedInteger('document_upload_allowance')->default(0);
            $table->decimal('document_storage_gb', 8, 2)->nullable(); // null = none
            $table->unsignedInteger('fyn_weekly_token_budget');
            $table->unsignedInteger('fyn_daily_hard_backstop');
            $table->enum('currency_display_mode', ['gbp_only', 'user_choice'])->default('gbp_only');
            $table->unsignedInteger('snapshot_surfacing_window_days')->default(90);
            $table->boolean('open_api_affordance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_configurations');
    }
};

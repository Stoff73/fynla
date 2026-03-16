<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lasting_powers_of_attorney')) {
            return;
        }

        Schema::create('lasting_powers_of_attorney', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('lpa_type', ['property_financial', 'health_welfare']);
            $table->enum('status', ['draft', 'completed', 'registered', 'uploaded'])->default('draft');
            $table->enum('source', ['created', 'uploaded'])->default('created');

            // Donor details
            $table->string('donor_full_name')->nullable();
            $table->date('donor_date_of_birth')->nullable();
            $table->string('donor_address_line_1')->nullable();
            $table->string('donor_address_line_2')->nullable();
            $table->string('donor_address_city')->nullable();
            $table->string('donor_address_county')->nullable();
            $table->string('donor_address_postcode', 10)->nullable();

            // Attorney decision type
            $table->enum('attorney_decision_type', ['jointly', 'jointly_and_severally', 'jointly_for_some'])->nullable();
            $table->text('jointly_for_some_details')->nullable();

            // Property & Financial only: when attorneys can act
            $table->enum('when_attorneys_can_act', ['while_has_capacity', 'only_when_lost_capacity'])->nullable();

            // Preferences and instructions
            $table->text('preferences')->nullable();
            $table->text('instructions')->nullable();

            // Health & Welfare only: life-sustaining treatment
            $table->enum('life_sustaining_treatment', ['can_consent', 'cannot_consent'])->nullable();

            // Certificate provider
            $table->string('certificate_provider_name')->nullable();
            $table->text('certificate_provider_address')->nullable();
            $table->string('certificate_provider_relationship')->nullable();
            $table->unsignedInteger('certificate_provider_known_years')->nullable();
            $table->text('certificate_provider_professional_details')->nullable();

            // Registration
            $table->date('registration_date')->nullable();
            $table->string('opg_reference')->nullable();
            $table->boolean('is_registered_with_opg')->default(false);

            // Document link
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'lpa_type']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lasting_powers_of_attorney');
    }
};

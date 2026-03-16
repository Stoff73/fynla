<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('will_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('will_id')->nullable()->constrained('wills')->nullOnDelete();
            $table->unsignedBigInteger('mirror_document_id')->nullable();
            $table->enum('will_type', ['simple', 'mirror']);
            $table->enum('status', ['draft', 'complete'])->default('draft');

            // Testator details (confirmed/edited from profile)
            $table->string('testator_full_name');
            $table->text('testator_address')->nullable();
            $table->date('testator_date_of_birth')->nullable();
            $table->string('testator_occupation')->nullable();

            // Executors: [{name, address, relationship, phone}]
            $table->json('executors')->nullable();

            // Guardians: [{name, address, relationship}]
            $table->json('guardians')->nullable();

            // Specific gifts: [{beneficiary_name, type, amount, description, conditions}]
            $table->json('specific_gifts')->nullable();

            // Residuary estate: [{beneficiary_name, percentage, substitution_beneficiary}]
            $table->json('residuary_estate')->nullable();

            // Funeral wishes
            $table->enum('funeral_preference', ['burial', 'cremation', 'no_preference'])->nullable();
            $table->text('funeral_wishes_notes')->nullable();

            // Digital assets
            $table->string('digital_executor_name')->nullable();
            $table->text('digital_assets_instructions')->nullable();

            // Survivorship clause period (days)
            $table->unsignedInteger('survivorship_days')->default(28);

            // Jurisdiction confirmation
            $table->enum('domicile_confirmed', ['england_wales', 'scotland', 'northern_ireland', 'other'])->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Self-referencing FK for mirror wills
            $table->foreign('mirror_document_id')
                ->references('id')
                ->on('will_documents')
                ->nullOnDelete();

            $table->index('user_id');
            $table->index('status');
        });

        // Add will_document_id to wills table
        Schema::table('wills', function (Blueprint $table) {
            $table->foreignId('will_document_id')->nullable()->after('last_reviewed_date')
                ->constrained('will_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wills', function (Blueprint $table) {
            $table->dropForeign(['will_document_id']);
            $table->dropColumn('will_document_id');
        });

        Schema::dropIfExists('will_documents');
    }
};

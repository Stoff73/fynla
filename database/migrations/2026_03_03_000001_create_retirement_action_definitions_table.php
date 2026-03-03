<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('retirement_action_definitions')) {
            return;
        }

        Schema::create('retirement_action_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('source', 20);
            $table->string('title_template');
            $table->text('description_template');
            $table->string('action_template')->nullable();
            $table->string('category', 50);
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('scope', ['account', 'portfolio'])->default('portfolio');
            $table->string('what_if_impact_type', 30)->default('default');
            $table->json('trigger_config');
            $table->boolean('is_enabled')->default(true);
            $table->smallInteger('sort_order')->default(100);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('source');
            $table->index('is_enabled');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retirement_action_definitions');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4 campaigns — reviewer-selectable alternate destinations for a post.
 * Default post destination is the source InsightArticle; a campaign overrides
 * it with a bespoke landing page + its own utm_campaign slug so we can
 * measure per-campaign performance in GA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('landing_url');
            $table->text('description')->nullable();
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active_from', 'active_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_campaigns');
    }
};

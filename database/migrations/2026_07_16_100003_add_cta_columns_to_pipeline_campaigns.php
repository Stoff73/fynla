<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 5 — add CTA copy to campaigns so the InsightCtaPanel component
 * can swap the default "Register free" CTA for the campaign-specific
 * variant when an article is linked to a campaign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('pipeline_campaigns', 'cta_heading')) {
                $table->string('cta_heading')->nullable()->after('description');
            }
            if (! Schema::hasColumn('pipeline_campaigns', 'cta_subheading')) {
                $table->string('cta_subheading', 500)->nullable()->after('cta_heading');
            }
            if (! Schema::hasColumn('pipeline_campaigns', 'cta_button_text')) {
                $table->string('cta_button_text', 60)->nullable()->after('cta_subheading');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_campaigns', function (Blueprint $table) {
            $table->dropColumn(['cta_heading', 'cta_subheading', 'cta_button_text']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_articles') || Schema::hasColumn('document_articles', 'pipeline_campaign_id')) {
            return;
        }

        Schema::table('document_articles', function (Blueprint $table) {
            $table->foreignId('pipeline_campaign_id')
                ->nullable()
                ->after('status')
                ->constrained('pipeline_campaigns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('document_articles', 'pipeline_campaign_id')) {
            return;
        }

        Schema::table('document_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pipeline_campaign_id');
        });
    }
};

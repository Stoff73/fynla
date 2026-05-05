<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('insight_articles', 'authors')) {
            return;
        }

        Schema::table('insight_articles', function (Blueprint $table): void {
            $table->json('authors')->nullable()->after('author_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('insight_articles', 'authors')) {
            return;
        }

        Schema::table('insight_articles', function (Blueprint $table): void {
            $table->dropColumn('authors');
        });
    }
};

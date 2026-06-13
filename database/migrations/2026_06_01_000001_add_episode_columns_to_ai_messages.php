<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->json('procedural_version')->nullable()->after('model_used');
            $table->char('semantic_snapshot_id', 64)->nullable()->after('procedural_version');
            $table->json('fetch_provenance')->nullable()->after('semantic_snapshot_id');
            $table->string('blob_md_path', 255)->nullable()->after('fetch_provenance');
            $table->char('blob_md_sha256', 64)->nullable()->after('blob_md_path');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'procedural_version',
                'semantic_snapshot_id',
                'fetch_provenance',
                'blob_md_path',
                'blob_md_sha256',
            ]);
        });
    }
};

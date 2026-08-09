<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 storage. Tracks the source video's provenance (Drive file + local
 * copy), the transcript we generated from it, the clips we produced, and the
 * running cost of the video-processing side of the pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('pipeline_articles', 'source_video_drive_file_id')) {
                $table->string('source_video_drive_file_id')->nullable()->after('video_url');
            }
            if (! Schema::hasColumn('pipeline_articles', 'source_video_drive_url')) {
                $table->string('source_video_drive_url')->nullable()->after('source_video_drive_file_id');
            }
            if (! Schema::hasColumn('pipeline_articles', 'source_video_path')) {
                $table->string('source_video_path')->nullable()->after('source_video_drive_url');
            }
            if (! Schema::hasColumn('pipeline_articles', 'source_video_duration_s')) {
                $table->integer('source_video_duration_s')->nullable()->after('source_video_path');
            }
            if (! Schema::hasColumn('pipeline_articles', 'transcript_path')) {
                $table->string('transcript_path')->nullable()->after('source_video_duration_s');
            }
            if (! Schema::hasColumn('pipeline_articles', 'clip_paths')) {
                $table->json('clip_paths')->nullable()->after('transcript_path');
            }
            if (! Schema::hasColumn('pipeline_articles', 'captions_burned')) {
                $table->boolean('captions_burned')->default(false)->after('clip_paths');
            }
            if (! Schema::hasColumn('pipeline_articles', 'clips_generated_at')) {
                $table->timestamp('clips_generated_at')->nullable()->after('captions_burned');
            }
            if (! Schema::hasColumn('pipeline_articles', 'video_cost_gbp')) {
                $table->decimal('video_cost_gbp', 8, 4)->nullable()->after('clips_generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_articles', function (Blueprint $table) {
            $table->dropColumn([
                'source_video_drive_file_id',
                'source_video_drive_url',
                'source_video_path',
                'source_video_duration_s',
                'transcript_path',
                'clip_paths',
                'captions_burned',
                'clips_generated_at',
                'video_cost_gbp',
            ]);
        });
    }
};

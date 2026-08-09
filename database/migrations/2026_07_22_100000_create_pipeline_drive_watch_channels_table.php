<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the active Google Drive push-notification channel: the channel id we
 * generated, Google's resource id (needed to stop it), the change-stream page
 * token to feed to changes.list, and when the channel expires (so a renewal
 * cron can re-register before Google drops it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pipeline_drive_watch_channels')) {
            return;
        }

        Schema::create('pipeline_drive_watch_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id')->unique();
            $table->string('resource_id')->nullable();
            $table->text('page_token');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_drive_watch_channels');
    }
};

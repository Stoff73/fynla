<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whitelist of users allowed to push articles to the live environment.
 * Existence of a row = permission granted. Admin manages this list via
 * the CMS UI (/admin/pipeline/publishers).
 *
 * Kept separate from the roles/permissions system so we can grant
 * publish-to-live without changing anyone's role, and so revocation is
 * a single-row delete rather than a role-config change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_publisher_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('granted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('granted_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_publisher_users');
    }
};

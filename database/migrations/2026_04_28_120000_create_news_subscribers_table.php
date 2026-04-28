<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('news_subscribers')) {
            return;
        }

        Schema::create('news_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('confirmation_token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('source', 32)->default('news_hub');
            $table->timestamps();

            // Composite index serves the active-subscriber scopes:
            //   confirmed: confirmed_at IS NOT NULL AND unsubscribed_at IS NULL
            //   pending:   confirmed_at IS NULL AND unsubscribed_at IS NULL
            // Leading column unsubscribed_at is the highly-selective filter
            // (most subscribers are not unsubscribed); confirmed_at narrows further.
            $table->index(['unsubscribed_at', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_subscribers');
    }
};

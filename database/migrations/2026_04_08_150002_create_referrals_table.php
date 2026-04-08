<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referrals')) {
            return;
        }

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_code', 20)->index();
            $table->string('referee_email', 255);
            $table->enum('status', ['pending', 'registered', 'converted', 'expired'])->default('pending');
            $table->boolean('bonus_applied')->default(false);
            $table->timestamp('referred_at');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'referee_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};

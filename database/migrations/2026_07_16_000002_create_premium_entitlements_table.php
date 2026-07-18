<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['apple', 'revolut']);
            $table->string('provider_reference', 191);
            $table->string('product_id', 100)->nullable();
            $table->enum('status', [
                'active',
                'grace_period',
                'billing_retry',
                'cancelled',
                'expired',
                'revoked',
            ]);
            $table->boolean('will_renew')->default(false);
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->dateTime('grace_period_ends_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('last_verified_at');
            $table->json('provider_metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_reference'],
                'premium_entitlements_provider_reference_unique'
            );
            $table->index(['user_id', 'status'], 'premium_entitlements_user_status_index');
            $table->index(['user_id', 'period_end'], 'premium_entitlements_user_period_end_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_entitlements');
    }
};

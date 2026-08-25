<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('premium_entitlement_id')
                ->constrained('premium_entitlements')
                ->cascadeOnDelete();
            $table->string('transaction_id', 191)->unique();
            $table->string('original_transaction_id', 191)->index();
            $table->uuid('app_account_token');
            $table->string('product_id', 100);
            $table->enum('environment', ['sandbox', 'production']);
            $table->dateTime('purchased_at');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('ownership_type', 32)->nullable();
            $table->string('transaction_reason', 32)->nullable();
            $table->char('signed_payload_sha256', 64);
            $table->dateTime('received_at');
            $table->dateTime('reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purchased_at'], 'apple_transactions_user_purchase_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_transactions');
    }
};

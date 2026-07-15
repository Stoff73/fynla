<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','trialing','active','cancelled','expired','past_due') NOT NULL DEFAULT 'pending'"
            );
        }

        DB::transaction(function (): void {
            $provisionalIds = DB::table('subscriptions')
                ->join('users', 'users.id', '=', 'subscriptions.user_id')
                ->where('subscriptions.status', 'trialing')
                ->whereNull('subscriptions.trial_started_at')
                ->whereNull('subscriptions.trial_ends_at')
                ->where('users.tier', 'free')
                ->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('payments')
                        ->whereColumn('payments.subscription_id', 'subscriptions.id')
                        ->where('payments.status', 'completed');
                })
                ->pluck('subscriptions.id');

            if ($provisionalIds->isNotEmpty()) {
                DB::table('subscriptions')
                    ->whereIn('id', $provisionalIds)
                    ->update(['status' => 'pending']);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('subscriptions')
                ->where('status', 'pending')
                ->update(['status' => 'trialing']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE subscriptions MODIFY COLUMN status ENUM('trialing','active','cancelled','expired','past_due') NOT NULL DEFAULT 'trialing'"
            );
        }
    }
};

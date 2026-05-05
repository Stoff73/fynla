<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscountCode;
use App\Models\FeedbackResponse;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use Illuminate\Console\Command;

class RunLifecycleEngineE2ECleanup extends Command
{
    protected $signature = 'lifecycle:e2e-cleanup';

    protected $description = 'Remove all lifecycle test users and their associated data';

    public function handle(): int
    {
        // Include trashed rows so orphans from aborted previous runs are
        // scooped up too — User and all the dependent module models use
        // SoftDeletes, and we want this command to leave the DB in a
        // genuinely clean state (not a shelf full of soft-deleted ghosts
        // still blocking unique email constraints on the next reseed).
        $testUsers = User::withTrashed()->where('is_lifecycle_test_user', true)->get();

        if ($testUsers->isEmpty()) {
            $this->info('No lifecycle test users to clean up.');

            return Command::SUCCESS;
        }

        $userIds = $testUsers->pluck('id')->all();

        $stats = [
            'lifecycle_email_log' => LifecycleEmailLog::whereIn('user_id', $userIds)->delete(),
            'feedback_responses' => FeedbackResponse::whereIn('user_id', $userIds)->delete(),
            'discount_codes' => DiscountCode::whereIn('user_id', $userIds)->delete(),
        ];

        // Explicit dependent force-deletes. All 7 module models use
        // SoftDeletes, so forceDelete() is required to fully remove the
        // rows rather than just flip deleted_at.
        foreach ($testUsers as $user) {
            $user->subscriptions()->withTrashed()->forceDelete();
            $user->properties()->withTrashed()->forceDelete();
            $user->dcPensions()->withTrashed()->forceDelete();
            $user->savingsAccounts()->withTrashed()->forceDelete();
            $user->investmentAccounts()->withTrashed()->forceDelete();
            $user->lifeInsurancePolicies()->withTrashed()->forceDelete();
            $user->goals()->withTrashed()->forceDelete();
        }

        $stats['users'] = User::withTrashed()->whereIn('id', $userIds)->forceDelete();

        $this->info('Cleanup complete:');
        foreach ($stats as $table => $count) {
            $this->info("  {$table}: {$count} rows deleted");
        }

        return Command::SUCCESS;
    }
}

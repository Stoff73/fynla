<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertTrialUsersToFree extends Command
{
    protected $signature = 'freemium:convert-trial-users {--dry-run : Report what would change without writing}';

    protected $description = 'Convert trial-origin users (never paid) to the Free tier; leave paid users untouched.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Trial-origin = users with a trialing OR expired subscription who have
        // NO completed payment. Paid-then-churned users (a completed payment)
        // are left on the existing churn/grace path.
        $candidates = User::query()
            ->where('is_preview_user', false)
            ->whereHas('subscription', function ($q) {
                $q->whereIn('status', ['trialing', 'expired']);
            })
            ->whereDoesntHave('subscription.payments', function ($q) {
                $q->where('status', 'completed');
            })
            ->get();

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Trial-origin users to convert: '.$candidates->count());

        if ($dryRun) {
            foreach ($candidates as $u) {
                $this->line("  would convert user {$u->id} ({$u->email}) → free");
            }

            return self::SUCCESS;
        }

        $converted = 0;
        foreach ($candidates as $user) {
            DB::transaction(function () use ($user, &$converted) {
                // Preserve a readable historical row without retaining schema-
                // blocking trial state, then halt any deletion countdown.
                Subscription::where('user_id', $user->id)
                    ->update([
                        'status' => 'expired',
                        'trial_started_at' => null,
                        'trial_ends_at' => null,
                        'data_retention_starts_at' => null,
                    ]);
                Subscription::where('user_id', $user->id)->delete(); // soft-delete (Subscription uses SoftDeletes)

                $user->update([
                    'tier' => 'free',
                    'plan' => 'free',
                    'trial_ends_at' => null,
                ]);
                $converted++;
            });
        }

        // Data-safety assertion: no converted user is left on a deletion path.
        $stranded = User::whereIn('id', $candidates->pluck('id'))
            ->whereHas('subscription', fn ($q) => $q->whereNotNull('data_retention_starts_at'))
            ->count();
        if ($stranded > 0) {
            $this->error("ABORTED CHECK: {$stranded} converted users still have a deletion countdown. Investigate.");

            return self::FAILURE;
        }

        $this->info("Converted {$converted} users to Free.");

        return self::SUCCESS;
    }
}

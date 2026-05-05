<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscountCode;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Lifecycle\LifecycleEngine;
use Database\Seeders\LifecycleTestSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class RunLifecycleEngineE2ETest extends Command
{
    protected $signature = 'lifecycle:e2e-test
        {--recipient= : Real email to send all test emails to}
        {--url= : Override the URL root used for magic links (defaults to APP_URL, or http://localhost:8000 when APP_URL is the bare http://localhost)}';

    protected $description = 'Run the lifecycle engine against dummy seeded users with email recipient override';

    public function handle(LifecycleEngine $engine): int
    {
        $recipient = $this->option('recipient');
        if (! $recipient) {
            $this->error('--recipient is required (e.g., --recipient=chris@fynla.org)');

            return Command::FAILURE;
        }

        $this->info("Running lifecycle e2e test, sending all emails to: {$recipient}");

        // Force the URL root for this run. Needed locally because APP_URL is
        // commonly set to "http://localhost" (no port) while the dev server
        // runs on :8000 — without this override, email magic links point at
        // port 80, which isn't listening, and the user sees "can't load page"
        // in Chrome. Production is unaffected because APP_URL there is a
        // fully-qualified https URL.
        $urlOverride = $this->option('url');
        if (! $urlOverride && config('app.url') === 'http://localhost') {
            $urlOverride = 'http://localhost:8000';
            $this->warn("APP_URL is bare 'http://localhost' — forcing URL root to {$urlOverride} so magic links work against php artisan serve.");
        }
        if ($urlOverride) {
            URL::forceRootUrl($urlOverride);
            $this->info("URL root for this run: {$urlOverride}");
        }

        // Override config for this run — the engine's dispatchEmail() reads
        // lifecycle.test_recipient_override and redirects test users' email
        // to this single inbox.
        config(['lifecycle.test_recipient_override' => $recipient]);

        // Seed 5 test users (idempotent — clears prior runs)
        $this->info('Seeding 5 test users...');
        (new LifecycleTestSeeder)->run();

        $testUserCount = User::where('is_lifecycle_test_user', true)->count();
        $this->info("Created {$testUserCount} test users.");

        // Run the engine in test mode so the is_lifecycle_test_user filter
        // flips from "reject" to "allow".
        $this->info('Running lifecycle engine in test mode...');
        $stats = $engine->setTestMode(true)->run();

        $this->info('--- Stats ---');
        foreach ($stats as $campaign => $counts) {
            $this->info(sprintf(
                '%s: %d sent, %d errored',
                $campaign,
                $counts['sent'] ?? 0,
                $counts['errored'] ?? 0
            ));
        }

        // Print log rows for manual inspection
        $this->info('--- Log rows ---');
        $logs = LifecycleEmailLog::with('user')
            ->whereIn('user_id', User::where('is_lifecycle_test_user', true)->pluck('id'))
            ->get();

        foreach ($logs as $log) {
            $this->info(sprintf(
                'Test user: %s (ID %d), campaign: %s, log row id: %d',
                $log->user->first_name ?? '(unknown)',
                $log->user_id,
                $log->campaign,
                $log->id,
            ));
        }

        // Print discount codes (only Campaign 2 produces these)
        $codes = DiscountCode::whereIn(
            'user_id',
            User::where('is_lifecycle_test_user', true)->pluck('id')
        )->get();

        if ($codes->isNotEmpty()) {
            $this->info('--- Per-user discount codes generated ---');
            foreach ($codes as $code) {
                $this->info(sprintf(
                    '  %s (user_id=%d, expires=%s)',
                    $code->code,
                    $code->user_id,
                    $code->expires_at?->toDateTimeString() ?? 'never'
                ));
            }
        }

        $this->newLine();
        $this->info("Done. Check {$recipient} inbox for emails.");
        $this->warn("REMEMBER to run 'php artisan lifecycle:e2e-cleanup' when finished testing.");

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use App\Models\UserConsent;
use App\Services\Consent\CookieConsentService;
use Illuminate\Console\Command;

class CleanupPendingRegistrations extends Command
{
    protected $signature = 'registrations:cleanup
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Delete expired pending registrations and unclaimable anonymous consent rows';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->purgePendingRegistrations($isDryRun);
        $this->purgeUnclaimableConsents($isDryRun);

        return Command::SUCCESS;
    }

    private function purgePendingRegistrations(bool $isDryRun): void
    {
        $query = PendingRegistration::where('expires_at', '<', now());
        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired pending registrations to clean up.');

            return;
        }

        if ($isDryRun) {
            $this->info("Would delete {$count} expired pending registration(s).");

            return;
        }

        $query->delete();
        $this->info("Deleted {$count} expired pending registration(s).");

        \Log::info('Expired pending registrations cleaned up', [
            'deleted_count' => $count,
        ]);
    }

    /**
     * W-0156 — the consent row that belongs to no user.
     *
     * F-0007 issues an anonymous consent row keyed to a server-issued
     * `subject_token`, so the record keeps the date consent was actually given
     * rather than one invented at registration. A visitor who goes on to register
     * has theirs claimed. **A visitor who never registers kept theirs for ever:**
     * `fyn:user:erase` operates on a user and these have none, and the six-year
     * episodic purge is a different store entirely, so nothing could reach them.
     *
     * **The lifetime is 365 days and it is derived, not chosen.** The row is
     * claimable only by a browser that can still present the token, and the cookie
     * carrying it expires after `CookieConsentService::LIFETIME_DAYS`. Past that
     * point the row cannot become anyone's consent by any route — it is retention
     * with no purpose left to serve. Reading the constant rather than repeating the
     * number means extending the cookie extends the claim window with it.
     *
     * **What is deliberately NOT purged.** A row with `superseded_at` set was
     * presented at a real registration and kept as evidence because the account
     * already held that consent type and version (F-0007's own rule). It carries a
     * null `user_id` and a token exactly like an abandoned row, which is why the
     * marker exists — without it this purge would destroy the evidence F-0007
     * refused to overwrite. A claimed row is untouched too: claiming nulls the
     * token, so it fails the `whereNotNull` on its own.
     */
    private function purgeUnclaimableConsents(bool $isDryRun): void
    {
        $cutoff = now()->subDays(CookieConsentService::LIFETIME_DAYS);

        $query = UserConsent::query()
            ->whereNull('user_id')
            ->whereNotNull('subject_token')
            ->whereNull('superseded_at')
            ->where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No unclaimable anonymous consent records to clean up.');

            return;
        }

        if ($isDryRun) {
            $this->info("Would delete {$count} unclaimable anonymous consent record(s).");

            return;
        }

        $query->delete();
        $this->info("Deleted {$count} unclaimable anonymous consent record(s).");

        \Log::info('Unclaimable anonymous consent records cleaned up', [
            'deleted_count' => $count,
            'lifetime_days' => CookieConsentService::LIFETIME_DAYS,
        ]);
    }
}

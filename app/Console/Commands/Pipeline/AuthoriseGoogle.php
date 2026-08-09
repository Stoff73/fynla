<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Services\Pipeline\Google\GoogleOAuthClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time OAuth authorisation for Google Drive + Sheets access.
 *
 * Prints a URL, waits for the user to visit it and click Allow, polls the
 * result cache set by GoogleOAuthController::callback, and reports the
 * connected account email when the refresh token has been stored.
 */
class AuthoriseGoogle extends Command
{
    protected $signature = 'pipeline:authorise-google
                            {--timeout=300 : Seconds to wait for the browser round-trip}';

    protected $description = 'Kick off the Google OAuth flow and store the refresh token';

    public function handle(GoogleOAuthClient $client): int
    {
        Cache::forget('pipeline.oauth.result');

        $state = Str::random(48);
        Cache::put('pipeline.oauth.state.'.$state, true, now()->addMinutes(15));

        $url = $client->issueAuthorisationUrl($state);

        $this->newLine();
        $this->line('  <fg=cyan>1.</> Open this URL in a browser signed in as the account that owns the Marketing Automation folder:');
        $this->newLine();
        $this->line('     '.$url);
        $this->newLine();
        $this->line('  <fg=cyan>2.</> Click <fg=green>Allow</> on the consent screen.');
        $this->line('  <fg=cyan>3.</> Google redirects to <fg=gray>/pipeline/oauth/google/callback</> — you should see a confirmation page.');
        $this->newLine();
        $this->info('Waiting for callback (Ctrl+C to abort)...');

        $timeout = max(30, (int) $this->option('timeout'));
        $deadline = now()->addSeconds($timeout);

        while (now()->lt($deadline)) {
            $result = Cache::get('pipeline.oauth.result');
            if ($result !== null) {
                Cache::forget('pipeline.oauth.result');
                $this->newLine();
                $this->info('✓ Authorised as '.($result['account_email'] ?? '(unknown account)'));
                $this->line('  Refresh token stored in pipeline_oauth_credentials.');
                $this->newLine();
                $this->line('  Next: <fg=cyan>php artisan pipeline:setup-tracker</> to create the tracker sheet.');

                return self::SUCCESS;
            }

            usleep(1_000_000);
        }

        Cache::forget('pipeline.oauth.state.'.$state);
        $this->error('Timed out after '.$timeout.'s. Re-run this command to try again.');

        return self::FAILURE;
    }
}

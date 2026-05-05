<?php

declare(strict_types=1);

namespace Tests\Browser;

use Illuminate\Support\Facades\Http;
use Tests\TestCase as BaseTestCase;

/**
 * Sprint 0 browser test harness — base case for every BS-NN scenario in
 * `tests/Browser/scenarios/`.
 *
 * # IMPORTANT: how this harness actually runs
 *
 * The Playwright tools (browser_navigate, browser_click, browser_fill_form,
 * etc.) are exposed via the Claude Code Playwright MCP — they are invoked
 * by the LLM during an interactive session, NOT by `vendor/bin/pest`.
 *
 * For that reason:
 *   - Each BS-NN scenario file is BOTH a Pest test (so the file is
 *     discoverable + the stub markTestSkipped() reminds anyone running
 *     the suite that the script needs Claude) AND an executable script
 *     that Claude reads and walks through step by step in a chat
 *     session, capturing screenshots into docs/sprint-0-verification/BS-NN/.
 *   - Vendor Pest will skip every scenario at runtime via the
 *     `markPendingInteractiveRun()` helper below, so the Browser suite
 *     adds zero failures to CI.
 *   - The Login + AssertSseEvents helpers are pure PHP utilities that
 *     can run in either context — Claude calls them from inside the
 *     scenario script via `tinker` / `artisan` shells, and Pest can
 *     run their pure-data assertions against captured fixtures.
 *
 * # Running interactively (S0.16b)
 *
 *   ./dev.sh                     # Laravel + Vite running on :8000 / :5173
 *   php artisan db:seed          # PreviewUserSeeder + factory rows
 *
 *   In a Claude session, paste the scenario file contents (or use the
 *   /loop or /schedule skill) and walk through the comment steps,
 *   driving Playwright tools at each `browser_*` call out.
 *
 * # Pre-flight invariants
 *
 *   - Localhost:8000 reachable (or `markBrowserUnavailable()` skips).
 *   - `php artisan db:seed` has been run for the current schema.
 *   - The active LLM provider matches the cache key (`ai_provider`).
 */
abstract class TestCase extends BaseTestCase
{
    protected string $rootUrl = 'http://localhost:8000';

    /**
     * Halt the current scenario as a Pest skip with a clear message
     * pointing at the interactive-execution gate (S0.16b). Use this in
     * every BS-NN stub so vendor Pest reports the suite cleanly.
     */
    protected function markPendingInteractiveRun(string $scenario): void
    {
        $this->markTestSkipped(
            "Browser scenario `{$scenario}` runs interactively via Playwright MCP "
            .'(see tests/Browser/README.md, Sprint 0 task S0.16b). vendor/bin/pest '
            .'does not drive a browser; this stub exists for discoverability.'
        );
    }

    /**
     * Diagnostic helper — invoke at the top of an interactive run to
     * confirm `./dev.sh` is up before walking the script. Returns true
     * if the root URL responded 2xx/3xx, false otherwise.
     *
     * Uses Laravel's HTTP client (Guzzle) rather than libcurl directly
     * so the helper sits inside the framework's shared timeout / TLS
     * configuration.
     */
    protected function browserHealthcheck(): bool
    {
        try {
            $response = Http::timeout(3)
                ->withoutRedirecting()
                ->get($this->rootUrl);

            $status = $response->status();

            return $status >= 200 && $status < 400;
        } catch (\Throwable) {
            return false;
        }
    }
}

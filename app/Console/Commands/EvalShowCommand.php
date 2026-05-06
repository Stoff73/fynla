<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EvalProviderRun;
use App\Models\EvalRecordingSession;
use Illuminate\Console\Command;

/**
 * Render the forensic view of an eval recording session.
 *
 * Usage:
 *   php artisan eval:show --session=42
 *   php artisan eval:show --scenario=advice_protection_cover --latest
 *   php artisan eval:show --scenario=advice_protection_cover --all
 *   php artisan eval:show --session=42 --full        # include full assistant text + tool args
 *
 * Without --full, long fields (assistant_text, tool args/results) are
 * truncated to keep the terminal output scannable.
 */
final class EvalShowCommand extends Command
{
    protected $signature = 'eval:show
        {--session= : Session id to render}
        {--scenario= : Scenario id (with --latest or --all)}
        {--latest : With --scenario: show only the most recent session}
        {--all : With --scenario: list every session for that scenario}
        {--full : Print full assistant text + tool args/results (no truncation)}';

    protected $description = 'Render the forensic view of an eval recording session.';

    public function handle(): int
    {
        $sessionId = $this->option('session');
        $scenarioId = $this->option('scenario');
        $full = (bool) $this->option('full');

        if ($sessionId !== null) {
            $session = EvalRecordingSession::with(['providerRuns', 'previewUser'])->find($sessionId);
            if ($session === null) {
                $this->error("Session #{$sessionId} not found.");

                return self::FAILURE;
            }

            $this->renderSession($session, $full);

            return self::SUCCESS;
        }

        if ($scenarioId !== null) {
            $query = EvalRecordingSession::where('scenario_id', $scenarioId)
                ->orderByDesc('started_at')
                ->with(['providerRuns', 'previewUser']);

            if ($this->option('latest')) {
                $session = $query->first();
                if ($session === null) {
                    $this->warn("No sessions found for scenario '{$scenarioId}'.");

                    return self::SUCCESS;
                }
                $this->renderSession($session, $full);

                return self::SUCCESS;
            }

            if ($this->option('all')) {
                $sessions = $query->get();
                if ($sessions->isEmpty()) {
                    $this->warn("No sessions found for scenario '{$scenarioId}'.");

                    return self::SUCCESS;
                }

                $this->info("Sessions for scenario '{$scenarioId}' ({$sessions->count()}):");
                $rows = [];
                foreach ($sessions as $s) {
                    $rows[] = [
                        $s->id,
                        $s->started_at?->toDateTimeString(),
                        $s->status,
                        $s->fynla_branch,
                        $s->fynla_sha,
                        $s->providerRuns->count(),
                    ];
                }
                $this->table(['ID', 'Started', 'Status', 'Branch', 'SHA', 'Runs'], $rows);
                $this->newLine();
                $this->line('View one: php artisan eval:show --session={$ID}');

                return self::SUCCESS;
            }

            $this->error('--scenario requires either --latest or --all.');

            return self::INVALID;
        }

        $this->error('Provide --session=N or --scenario=ID.');

        return self::INVALID;
    }

    private function renderSession(EvalRecordingSession $session, bool $full): void
    {
        $this->info(str_repeat('=', 70));
        $this->info("Session #{$session->id} — {$session->scenario_id}");
        $this->info(str_repeat('=', 70));

        $this->line('Status:      '.$session->status);
        $this->line('Started:     '.$session->started_at?->toDateTimeString());
        $this->line('Completed:   '.($session->completed_at?->toDateTimeString() ?? '—'));
        $this->line('Branch / SHA: '.$session->fynla_branch.' @ '.$session->fynla_sha);
        $this->line('Preview user: id='.$session->preview_user_id.' email='.($session->previewUser?->email ?? '?'));

        $this->newLine();
        $this->info('— Start state (the data Fyn was working from) —');
        $this->renderSnapshot($session->start_state_snapshot ?? [], $full);

        $this->newLine();
        $this->info('— Provider runs —');

        foreach ($session->providerRuns as $run) {
            $this->renderRun($run, $full);
        }

        if ($session->providerRuns->count() >= 2) {
            $this->renderDelta($session->providerRuns->all(), $full);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function renderSnapshot(array $snapshot, bool $full): void
    {
        $user = $snapshot['user'] ?? [];
        if ($user !== []) {
            $line = 'user.id='.($user['id'] ?? '?');
            foreach (['first_name', 'surname', 'marital_status', 'date_of_birth', 'annual_income', 'onboarding_completed', 'onboarding_fyn_step'] as $f) {
                if (array_key_exists($f, $user) && $user[$f] !== null) {
                    $line .= " {$f}=".$this->scalarize($user[$f]);
                }
            }
            $this->line('  '.$line);
        }

        foreach ($snapshot as $key => $value) {
            if ($key === 'user' || $key === 'expenditure_profile') {
                continue;
            }
            if (! is_array($value) || $value === []) {
                continue;
            }

            $count = count($value);
            $this->line("  {$key} ({$count}):");
            foreach ($value as $row) {
                $rowSummary = '    [#'.($row['id'] ?? '?').']';
                foreach (['provider', 'account_type', 'scheme_type', 'property_type', 'sum_assured', 'balance', 'current_value', 'monthly_premium'] as $f) {
                    if (array_key_exists($f, $row) && $row[$f] !== null) {
                        $rowSummary .= " {$f}=".$this->scalarize($row[$f]);
                    }
                }
                $this->line($rowSummary);
            }
        }

        $exp = $snapshot['expenditure_profile'] ?? null;
        if (is_array($exp)) {
            $line = '  expenditure_profile [#'.($exp['id'] ?? '?').']';
            foreach (['monthly_total', 'monthly_essentials', 'monthly_lifestyle'] as $f) {
                if (array_key_exists($f, $exp) && $exp[$f] !== null) {
                    $line .= " {$f}=".$this->scalarize($exp[$f]);
                }
            }
            $this->line($line);
        }
    }

    private function renderRun(EvalProviderRun $run, bool $full): void
    {
        $this->newLine();
        $this->info(str_repeat('-', 70));
        $this->info("Run #{$run->id} — {$run->provider} / {$run->model}");
        $this->info(str_repeat('-', 70));
        $this->line('Conversation: id='.$run->conversation_id);
        $this->line('Duration:    '.$run->duration_ms.'ms');
        $this->line('SSE events:  '.$run->sse_event_count.' ('.$this->summariseEventTypes($run->sse_event_types ?? []).')');
        $this->line('Fixture:     '.($run->fixture_path ?? '—'));

        $this->newLine();
        $this->line('Question:');
        $this->line('  > '.$run->user_message);

        $this->newLine();
        $this->line('Tool calls:');
        $toolCalls = $run->tool_calls ?? [];
        if ($toolCalls === []) {
            $this->line('  (none)');
        } else {
            foreach ($toolCalls as $i => $call) {
                $args = $full
                    ? json_encode($call['args'] ?? [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                    : mb_strimwidth(json_encode($call['args'] ?? []) ?: '{}', 0, 80, '…');
                $this->line('  '.($i + 1).'. '.($call['name'] ?? '?').'  args='.$args);
                if ($full) {
                    $resultJson = json_encode($call['result'] ?? null, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                    $this->line('     result: '.($resultJson === false ? '(unencodable)' : mb_strimwidth($resultJson, 0, 600, '…')));
                }
            }
        }

        $this->newLine();
        $this->line('Assistant response:');
        $text = (string) ($run->assistant_text ?? '');
        if ($text === '') {
            $this->line('  (empty)');
        } else {
            $rendered = $full ? $text : mb_strimwidth($text, 0, 400, '…');
            foreach (explode("\n", $rendered) as $ln) {
                $this->line('  '.$ln);
            }
        }

        $this->newLine();
        $writes = $run->db_writes_made ?? [];
        $createdN = count($writes['created'] ?? []);
        $updatedN = count($writes['updated'] ?? []);
        $deletedN = count($writes['deleted'] ?? []);
        $this->line("DB writes:   created={$createdN} updated={$updatedN} deleted={$deletedN}");
        if ($full && ($createdN + $updatedN + $deletedN) > 0) {
            foreach (['created', 'updated', 'deleted'] as $kind) {
                foreach (($writes[$kind] ?? []) as $w) {
                    $this->line("  {$kind}: ".($w['table'] ?? '?').'#'.($w['id'] ?? '?').' '.json_encode($w['fields'] ?? $w['data'] ?? []));
                }
            }
        }

        $hits = $run->forbidden_hits ?? [];
        $this->line('Forbidden:   '.($hits === [] ? 'none' : count($hits).' hit(s) — '.implode('; ', $hits)));
    }

    /**
     * @param  list<EvalProviderRun>  $runs
     */
    private function renderDelta(array $runs, bool $full): void
    {
        $this->newLine();
        $this->info(str_repeat('=', 70));
        $this->info('DELTA');
        $this->info(str_repeat('=', 70));

        $first = $runs[0];
        $second = $runs[1];

        $firstTools = array_column($first->tool_calls ?? [], 'name');
        $secondTools = array_column($second->tool_calls ?? [], 'name');
        $toolsMatch = $firstTools === $secondTools;

        $this->line('Tool sequence: '.($toolsMatch ? 'IDENTICAL' : 'DIVERGENT'));
        if (! $toolsMatch) {
            $this->line("  {$first->provider}: ".implode(' → ', $firstTools ?: ['—']));
            $this->line("  {$second->provider}: ".implode(' → ', $secondTools ?: ['—']));
        }

        $firstLen = mb_strlen((string) ($first->assistant_text ?? ''));
        $secondLen = mb_strlen((string) ($second->assistant_text ?? ''));
        $delta = $secondLen - $firstLen;
        $deltaPct = $firstLen > 0 ? round(($delta / $firstLen) * 100, 1) : 0;
        $this->line("Response length: {$firstLen} ({$first->provider}) vs {$secondLen} ({$second->provider})  Δ {$delta} ({$deltaPct}%)");

        $firstWrites = array_sum(array_map(fn ($k) => count(($first->db_writes_made ?? [])[$k] ?? []), ['created', 'updated', 'deleted']));
        $secondWrites = array_sum(array_map(fn ($k) => count(($second->db_writes_made ?? [])[$k] ?? []), ['created', 'updated', 'deleted']));
        $this->line("DB writes:   {$firstWrites} ({$first->provider}) vs {$secondWrites} ({$second->provider})");

        $firstDuration = (int) ($first->duration_ms ?? 0);
        $secondDuration = (int) ($second->duration_ms ?? 0);
        $this->line("Duration:    {$firstDuration}ms ({$first->provider}) vs {$secondDuration}ms ({$second->provider})");

        if (! $full) {
            $this->newLine();
            $this->line('(Run with --full for unabridged tool args, results, and write details.)');
        }
    }

    /**
     * @param  array<string, int>  $types
     */
    private function summariseEventTypes(array $types): string
    {
        if ($types === []) {
            return '—';
        }
        $parts = [];
        foreach ($types as $type => $count) {
            $parts[] = "{$type}={$count}";
        }

        return implode(', ', $parts);
    }

    private function scalarize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '?';
        }

        return (string) $value;
    }
}

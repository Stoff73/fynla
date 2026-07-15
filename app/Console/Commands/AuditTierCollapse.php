<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tiers\TierCollapsePreflight;
use Illuminate\Console\Command;

class AuditTierCollapse extends Command
{
    protected $signature = 'subscriptions:audit-tier-collapse {--json : Print the stable machine-readable audit object}';

    protected $description = 'Audit whether tier identity can be collapsed to Free and Premium without changing an entitled payer.';

    public function __construct(
        private readonly TierCollapsePreflight $preflight,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->audit();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Metric', 'Count'],
                collect($result)
                    ->except('safe_to_collapse')
                    ->map(fn (int $count, string $metric): array => [$metric, $count])
                    ->values()
                    ->all()
            );

            if ($result['safe_to_collapse']) {
                $this->info('Tier collapse preflight passed.');
            } else {
                $this->error('Tier collapse blocked: the cutover preflight found unresolved state.');
            }
        }

        return $result['safe_to_collapse'] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, int|bool> */
    public function audit(): array
    {
        return $this->preflight->audit();
    }
}

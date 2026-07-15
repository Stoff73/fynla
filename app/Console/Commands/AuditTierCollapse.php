<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AuditTierCollapse extends Command
{
    private const RETIRED_TIERS = ['tier1', 'tier2', 'tier3'];

    protected $signature = 'subscriptions:audit-tier-collapse {--json : Print the stable machine-readable audit object}';

    protected $description = 'Audit whether tier identity can be collapsed to Free and Premium without changing an entitled payer.';

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
                $this->error('Tier collapse blocked: a real account still has paid entitlement.');
            }
        }

        return $result['safe_to_collapse'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{active_paid_subscriptions:int, active_paid_users:int, completed_payments:int, retired_tier_user_rows:int, retired_tier_subscription_rows:int, safe_to_collapse:bool}
     */
    public function audit(): array
    {
        $entitled = $this->entitledPaidSubscriptions();
        $activePaidSubscriptions = (clone $entitled)->count('subscriptions.id');
        $activePaidUsers = (clone $entitled)->distinct()->count('subscriptions.user_id');

        return [
            'active_paid_subscriptions' => $activePaidSubscriptions,
            'active_paid_users' => $activePaidUsers,
            'completed_payments' => DB::table('payments')->where('status', 'completed')->count(),
            'retired_tier_user_rows' => DB::table('users')
                ->where(function (Builder $query) {
                    $query->whereIn('tier', self::RETIRED_TIERS)
                        ->orWhereIn('plan', self::RETIRED_TIERS);
                })
                ->count(),
            'retired_tier_subscription_rows' => DB::table('subscriptions')
                ->whereIn('plan', self::RETIRED_TIERS)
                ->count(),
            'safe_to_collapse' => $activePaidSubscriptions === 0,
        ];
    }

    private function entitledPaidSubscriptions(): Builder
    {
        return DB::table('subscriptions')
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->where('subscriptions.plan', '!=', 'free')
            ->where('users.is_preview_user', false)
            ->where('users.is_lifecycle_test_user', false)
            ->where(function (Builder $query) {
                $query->where('subscriptions.status', 'active')
                    ->orWhere(function (Builder $query) {
                        $query->whereIn('subscriptions.status', ['cancelled', 'past_due'])
                            ->where('subscriptions.current_period_end', '>', now());
                    });
            });
    }
}

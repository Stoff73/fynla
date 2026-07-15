<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AuditTrialSubscriptionRemnants extends Command
{
    protected $signature = 'subscriptions:audit-trial-remnants {--json : Print the stable machine-readable audit object}';

    protected $description = 'Audit trial-shaped subscription data before removing trial compatibility.';

    public function handle(): int
    {
        $result = $this->audit();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Shape', 'Count'],
                [
                    ['provisional_shape', $result['provisional_shape']],
                    ['historical_trial_shape', $result['historical_trial_shape']],
                    ['paid_shape', $result['paid_shape']],
                ],
            );

            if ($result['safe_to_remove_trial']) {
                $this->info('No trial remnants remain.');
            } else {
                $this->error('Trial compatibility removal is blocked by historical trial-shaped rows.');
            }
        }

        return $result['safe_to_remove_trial'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{provisional_shape:int, historical_trial_shape:int, paid_shape:int, safe_to_remove_trial:bool}
     */
    public function audit(): array
    {
        $provisionalShape = DB::table('subscriptions')
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
            ->count('subscriptions.id');

        $historicalTrialShape = DB::table('subscriptions')
            ->where(function (Builder $query): void {
                $query->where('status', 'trialing')
                    ->orWhereNotNull('trial_started_at')
                    ->orWhereNotNull('trial_ends_at');
            })
            ->count();

        $paidShape = DB::table('subscriptions')
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.subscription_id', 'subscriptions.id')
                    ->where('payments.status', 'completed');
            })
            ->count();

        return [
            'provisional_shape' => $provisionalShape,
            'historical_trial_shape' => $historicalTrialShape,
            'paid_shape' => $paidShape,
            'safe_to_remove_trial' => $historicalTrialShape === 0,
        ];
    }
}

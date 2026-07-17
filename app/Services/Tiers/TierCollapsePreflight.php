<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TierCollapsePreflight
{
    private const RETIRED_TIERS = ['tier1', 'tier2', 'tier3'];

    /** @return array<string, int|bool> */
    public function audit(): array
    {
        $entitled = $this->entitledPaidSubscriptions();
        $activePaidSubscriptions = (clone $entitled)->count('subscriptions.id');
        $activePaidUsers = (clone $entitled)->distinct()->count('subscriptions.user_id');
        $liveProviderAgreements = $this->realSubscriptions()
            ->where(function (Builder $query): void {
                $query->where('subscriptions.auto_renew', true)
                    ->orWhereNotNull('subscriptions.revolut_subscription_id');
            })
            ->count('subscriptions.id');
        $duplicateProviderOrderIds = DB::table('payments')
            ->whereNotNull('revolut_order_id')
            ->where('revolut_order_id', '!=', 'pending')
            ->select('revolut_order_id')
            ->groupBy('revolut_order_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $duplicateInvoicePayments = $this->duplicateGroups('invoices', 'payment_id');
        $duplicateDiscountUsagePayments = $this->duplicateGroups('discount_code_usages', 'payment_id');
        $duplicateReferralReferees = $this->duplicateGroups('referrals', 'referee_id');
        $multiplePendingCheckoutUsers = DB::table('payments')
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $safe = $activePaidSubscriptions === 0
            && $liveProviderAgreements === 0
            && $duplicateProviderOrderIds === 0
            && $duplicateInvoicePayments === 0
            && $duplicateDiscountUsagePayments === 0
            && $duplicateReferralReferees === 0
            && $multiplePendingCheckoutUsers === 0;

        return [
            'active_paid_subscriptions' => $activePaidSubscriptions,
            'active_paid_users' => $activePaidUsers,
            'live_provider_agreements' => $liveProviderAgreements,
            'completed_payments' => DB::table('payments')->where('status', 'completed')->count(),
            'retired_tier_user_rows' => DB::table('users')
                ->where(function (Builder $query): void {
                    $query->whereIn('tier', self::RETIRED_TIERS)
                        ->orWhereIn('plan', self::RETIRED_TIERS);
                })
                ->count(),
            'retired_tier_subscription_rows' => DB::table('subscriptions')
                ->whereIn('plan', self::RETIRED_TIERS)
                ->count(),
            'duplicate_provider_order_ids' => $duplicateProviderOrderIds,
            'duplicate_invoice_payments' => $duplicateInvoicePayments,
            'duplicate_discount_usage_payments' => $duplicateDiscountUsagePayments,
            'duplicate_referral_referees' => $duplicateReferralReferees,
            'multiple_pending_checkout_users' => $multiplePendingCheckoutUsers,
            'safe_to_collapse' => $safe,
        ];
    }

    /** @return array<string, int|bool> */
    public function assertSafe(): array
    {
        $result = $this->audit();
        if (! $result['safe_to_collapse']) {
            throw new RuntimeException(
                'Tier collapse blocked by preflight: '.json_encode($result, JSON_THROW_ON_ERROR)
            );
        }

        return $result;
    }

    private function realSubscriptions(): Builder
    {
        return DB::table('subscriptions')
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->whereNull('subscriptions.deleted_at')
            ->where('users.is_preview_user', false)
            ->where('users.is_lifecycle_test_user', false);
    }

    private function entitledPaidSubscriptions(): Builder
    {
        return $this->realSubscriptions()
            ->where('subscriptions.plan', '!=', 'free')
            ->where(function (Builder $query): void {
                $query->where('subscriptions.status', 'active')
                    ->orWhere(function (Builder $query): void {
                        $query->whereIn('subscriptions.status', ['cancelled', 'past_due'])
                            ->where('subscriptions.current_period_end', '>', now());
                    });
            });
    }

    private function duplicateGroups(string $table, string $column): int
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }
}

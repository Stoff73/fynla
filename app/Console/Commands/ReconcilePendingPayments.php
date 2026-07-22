<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FireAwinConversionJob;
use App\Models\Payment;
use App\Services\Payment\PaymentFinalizationService;
use App\Services\Payment\PaymentSettlementService;
use App\Services\Payment\RevolutOrderVerifier;
use App\Services\Payment\RevolutService;
use App\Services\Tiers\TierCollapseLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile-pending {--older-than=15 : Minimum checkout age in minutes}';

    protected $description = 'Reconcile stale pending payment orders with Revolut';

    public function handle(
        RevolutService $revolutService,
        RevolutOrderVerifier $orderVerifier,
        PaymentSettlementService $settlementService,
        PaymentFinalizationService $finalizationService,
        TierCollapseLock $tierCollapseLock,
    ): int {
        $olderThan = max(5, (int) $this->option('older-than'));
        $payments = Payment::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($olderThan))
            ->orderBy('id')
            ->get();

        $reconciled = 0;
        $released = 0;
        $recovered = 0;

        $recoverable = Payment::query()
            ->where('status', 'completed')
            ->where(function ($query): void {
                $query->whereNull('financial_finalized_at')
                    ->orWhereNull('confirmation_email_sent_at');

                if (config('awin.enabled')) {
                    $query->orWhereNull('awin_fired_at');
                }
            })
            ->orderBy('id')
            ->get();

        foreach ($recoverable as $payment) {
            try {
                $outcome = $tierCollapseLock->run(function () use ($payment, $finalizationService): bool {
                    $current = Payment::query()->findOrFail($payment->id);
                    if ($current->status !== 'completed') {
                        return false;
                    }

                    $finalized = $finalizationService->finalize($current);
                    if (config('awin.enabled') && ! $finalized->user->is_admin
                        && $finalized->awin_fired_at === null) {
                        FireAwinConversionJob::dispatch($finalized->id);
                    }

                    return true;
                }, "user:{$payment->user_id}");

                if ($outcome) {
                    $recovered++;
                }
            } catch (\Throwable $e) {
                Log::error('Completed payment finalization recovery failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($payments as $payment) {
            try {
                $remoteOrder = str_starts_with($payment->revolut_order_id, 'pending_')
                    ? $revolutService->findOrderByMerchantReference("payment_{$payment->id}")
                    : $revolutService->getOrder($payment->revolut_order_id);
            } catch (\Throwable $e) {
                Log::warning('Pending payment reconciliation request failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            try {
                $outcome = $tierCollapseLock->run(function () use (
                    $payment,
                    $remoteOrder,
                    $orderVerifier,
                    $settlementService,
                    $finalizationService,
                ): string {
                    $current = Payment::query()->findOrFail($payment->id);
                    if ($current->status !== 'pending') {
                        return 'unchanged';
                    }
                    if ($current->revolut_order_id !== $payment->revolut_order_id
                        || $current->getRawOriginal('updated_at') !== $payment->getRawOriginal('updated_at')) {
                        return 'unchanged';
                    }

                    if ($remoteOrder === null) {
                        $misses = $current->reconciliation_misses + 1;
                        $current->update([
                            'reconciliation_misses' => $misses,
                            'last_reconciled_at' => now(),
                            'status' => $misses >= 2 ? 'failed' : 'pending',
                        ]);

                        return $misses >= 2 ? 'released' : 'unchanged';
                    }

                    $current->update([
                        'revolut_order_id' => $remoteOrder['id'],
                        'revolut_payment_data' => $remoteOrder,
                        'reconciliation_misses' => 0,
                        'last_reconciled_at' => now(),
                    ]);

                    $remoteState = strtolower((string) ($remoteOrder['state'] ?? 'unknown'));
                    if (in_array($remoteState, ['cancelled', 'failed'], true)) {
                        $current->update(['status' => 'failed']);

                        return 'released';
                    }

                    if ($remoteState !== 'completed') {
                        return 'reconciled';
                    }

                    $verificationFailure = $orderVerifier->completedOrderFailure($current, $remoteOrder);
                    if ($verificationFailure !== null) {
                        throw new \RuntimeException($verificationFailure);
                    }

                    $settled = $settlementService->settle($current, $remoteOrder);
                    $finalized = $finalizationService->finalize($settled);
                    if (config('awin.enabled') && ! $finalized->user->is_admin) {
                        FireAwinConversionJob::dispatch($finalized->id);
                    }

                    return 'reconciled';
                }, "user:{$payment->user_id}");

                if ($outcome === 'released') {
                    $released++;
                } elseif ($outcome === 'reconciled') {
                    $reconciled++;
                }
            } catch (\Throwable $e) {
                Log::error('Pending payment reconciliation could not be applied', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(
            "Reconciled {$reconciled} pending payment(s); recovered {$recovered} completed payment(s); released {$released} stale checkout(s)."
        );

        return self::SUCCESS;
    }
}

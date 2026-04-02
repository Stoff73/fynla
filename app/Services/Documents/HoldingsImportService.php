<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DCPension;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HoldingsImportService
{
    /**
     * Find an existing account that matches the extracted sheet data.
     */
    public function findMatchingAccount(User $user, string $category, array $accountData): ?Model
    {
        $type = $accountData['account_type'] ?? null;
        $provider = $accountData['provider'] ?? null;

        return match ($category) {
            'investment_holdings' => $this->matchInvestmentAccount($user, $type, $provider),
            'pension_holdings' => $this->matchPension($user, $provider),
            default => null,
        };
    }

    /**
     * Diff imported holdings against existing holdings on an account.
     *
     * @return array Each item has 'status' (add|update|unchanged|not_in_import) + holding data
     */
    public function diffHoldings(Model $account, array $importedHoldings): array
    {
        $existing = $account->holdings()->get();
        $result = [];
        $matchedExistingIds = [];

        foreach ($importedHoldings as $imported) {
            $match = $this->findMatchingHolding($existing, $imported);

            if ($match) {
                $matchedExistingIds[] = $match->id;
                $hasChanges = $this->holdingHasChanges($match, $imported);

                $result[] = array_merge($imported, [
                    'status' => $hasChanges ? 'update' : 'unchanged',
                    'existing_id' => $match->id,
                    'existing_quantity' => $match->quantity,
                    'existing_value' => $match->current_value,
                ]);
            } else {
                $result[] = array_merge($imported, [
                    'status' => 'add',
                    'existing_id' => null,
                ]);
            }
        }

        // Holdings in Fynla but not in import
        foreach ($existing as $existingHolding) {
            if (! in_array($existingHolding->id, $matchedExistingIds)) {
                $result[] = [
                    'status' => 'not_in_import',
                    'existing_id' => $existingHolding->id,
                    'security_name' => $existingHolding->security_name,
                    'ticker' => $existingHolding->ticker,
                    'isin' => $existingHolding->isin,
                    'quantity' => $existingHolding->quantity,
                    'current_value' => $existingHolding->current_value,
                ];
            }
        }

        return $result;
    }

    /**
     * Apply confirmed holdings import to an account.
     */
    public function applyHoldings(Model $account, array $confirmedHoldings): array
    {
        $created = 0;
        $updated = 0;
        $removed = 0;

        DB::transaction(function () use ($account, $confirmedHoldings, &$created, &$updated, &$removed) {
            foreach ($confirmedHoldings as $holding) {
                $status = $holding['status'] ?? 'add';

                if ($status === 'add') {
                    Holding::create([
                        'holdable_id' => $account->id,
                        'holdable_type' => get_class($account),
                        'security_name' => $holding['security_name'] ?? null,
                        'ticker' => $holding['ticker'] ?? null,
                        'isin' => $holding['isin'] ?? null,
                        'asset_type' => $holding['asset_type'] ?? 'fund',
                        'quantity' => $holding['quantity'] ?? null,
                        'current_price' => $holding['current_price'] ?? null,
                        'current_value' => $holding['current_value'] ?? null,
                        'purchase_price' => $holding['purchase_price'] ?? null,
                        'cost_basis' => $holding['cost_basis'] ?? null,
                    ]);
                    $created++;
                } elseif ($status === 'update' && isset($holding['existing_id'])) {
                    Holding::where('id', $holding['existing_id'])->update([
                        'quantity' => $holding['quantity'] ?? null,
                        'current_price' => $holding['current_price'] ?? null,
                        'current_value' => $holding['current_value'] ?? null,
                    ]);
                    $updated++;
                } elseif ($status === 'remove' && isset($holding['existing_id'])) {
                    Holding::where('id', $holding['existing_id'])->delete();
                    $removed++;
                }
            }
        });

        return compact('created', 'updated', 'removed');
    }

    private function matchInvestmentAccount(User $user, ?string $type, ?string $provider): ?InvestmentAccount
    {
        $query = InvestmentAccount::where('user_id', $user->id);

        if ($type) {
            $query->where('account_type', $type);
        }
        if ($provider) {
            $query->where('provider', 'LIKE', "%{$provider}%");
        }

        return $query->first();
    }

    private function matchPension(User $user, ?string $provider): ?DCPension
    {
        $query = DCPension::where('user_id', $user->id);

        if ($provider) {
            $query->where('provider', 'LIKE', "%{$provider}%");
        }

        return $query->first();
    }

    private function findMatchingHolding($existingHoldings, array $imported): ?Holding
    {
        // Match by ISIN first (most reliable)
        if (! empty($imported['isin'])) {
            $match = $existingHoldings->firstWhere('isin', $imported['isin']);
            if ($match) {
                return $match;
            }
        }

        // Match by ticker
        if (! empty($imported['ticker'])) {
            $match = $existingHoldings->first(function ($h) use ($imported) {
                return $h->ticker && strtoupper($h->ticker) === strtoupper($imported['ticker']);
            });
            if ($match) {
                return $match;
            }
        }

        // Match by security name (fuzzy)
        if (! empty($imported['security_name'])) {
            $match = $existingHoldings->first(function ($h) use ($imported) {
                return $h->security_name &&
                    str_contains(
                        strtolower($h->security_name),
                        strtolower(substr($imported['security_name'], 0, 15))
                    );
            });
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function holdingHasChanges(Holding $existing, array $imported): bool
    {
        if (isset($imported['quantity']) && (float) $imported['quantity'] !== (float) $existing->quantity) {
            return true;
        }
        if (isset($imported['current_value']) && abs((float) $imported['current_value'] - (float) $existing->current_value) > 0.01) {
            return true;
        }

        return false;
    }
}

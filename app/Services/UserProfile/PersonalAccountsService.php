<?php

declare(strict_types=1);

namespace App\Services\UserProfile;

use App\Models\SavingsAccount;
use App\Models\User;
use Carbon\Carbon;

class PersonalAccountsService
{
    /**
     * Calculate Profit and Loss statement for the user
     *
     * P&L = Total Income - Total Expenses
     */
    public function calculateProfitAndLoss(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // Load relationships
        $user->load(['properties', 'mortgages']);

        // Calculate income line items
        $income = [
            [
                'line_item' => 'Employment Income',
                'category' => 'income',
                'amount' => $user->annual_employment_income ?? 0,
            ],
            [
                'line_item' => 'Self-Employment Income',
                'category' => 'income',
                'amount' => $user->annual_self_employment_income ?? 0,
            ],
            [
                'line_item' => 'Rental Income',
                'category' => 'income',
                'amount' => $user->annual_rental_income ?? 0,
            ],
            [
                'line_item' => 'Dividend Income',
                'category' => 'income',
                'amount' => $user->annual_dividend_income ?? 0,
            ],
            [
                'line_item' => 'Other Income',
                'category' => 'income',
                'amount' => $user->annual_other_income ?? 0,
            ],
        ];

        $totalIncome = collect($income)->sum('amount');

        // Calculate expense line items
        $mortgagePayments = $user->mortgages->sum(function ($mortgage) {
            return ($mortgage->monthly_payment ?? 0) * 12;
        });

        $propertyExpenses = $user->properties->sum(function ($property) {
            return ($property->annual_service_charge ?? 0) +
                   ($property->annual_ground_rent ?? 0) +
                   ($property->annual_insurance ?? 0) +
                   ($property->annual_maintenance_reserve ?? 0) +
                   ($property->other_annual_costs ?? 0);
        });

        // Calculate personal/living expenses from user expenditure fields (annualized)
        $livingExpenses = ($user->monthly_expenditure ?? 0) * 12;

        $expenses = [
            [
                'line_item' => 'Mortgage Payments',
                'category' => 'expense',
                'amount' => $mortgagePayments,
            ],
            [
                'line_item' => 'Property Expenses',
                'category' => 'expense',
                'amount' => $propertyExpenses,
            ],
            [
                'line_item' => 'Living Expenses',
                'category' => 'expense',
                'amount' => $livingExpenses,
            ],
        ];

        $totalExpenses = collect($expenses)->sum('amount');

        $netProfitLoss = $totalIncome - $totalExpenses;

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'income' => $income,
            'total_income' => $totalIncome,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_profit_loss' => $netProfitLoss,
        ];
    }

    /**
     * Calculate Cashflow statement for the user
     *
     * Cashflow = Cash Inflows - Cash Outflows
     */
    public function calculateCashflow(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // Load relationships
        $user->load(['properties', 'mortgages', 'dcPensions']);

        // Cash inflows
        $inflows = [
            [
                'line_item' => 'Employment Income',
                'category' => 'cash_inflow',
                'amount' => $user->annual_employment_income ?? 0,
            ],
            [
                'line_item' => 'Self-Employment Income',
                'category' => 'cash_inflow',
                'amount' => $user->annual_self_employment_income ?? 0,
            ],
            [
                'line_item' => 'Rental Income',
                'category' => 'cash_inflow',
                'amount' => $user->annual_rental_income ?? 0,
            ],
            [
                'line_item' => 'Dividend Income',
                'category' => 'cash_inflow',
                'amount' => $user->annual_dividend_income ?? 0,
            ],
            [
                'line_item' => 'Other Income',
                'category' => 'cash_inflow',
                'amount' => $user->annual_other_income ?? 0,
            ],
        ];

        $totalInflows = collect($inflows)->sum('amount');

        // Cash outflows
        $mortgagePayments = $user->mortgages->sum(function ($mortgage) {
            return ($mortgage->monthly_payment ?? 0) * 12;
        });

        $propertyExpenses = $user->properties->sum(function ($property) {
            return ($property->annual_service_charge ?? 0) +
                   ($property->annual_ground_rent ?? 0) +
                   ($property->annual_insurance ?? 0) +
                   ($property->annual_maintenance_reserve ?? 0) +
                   ($property->other_annual_costs ?? 0);
        });

        // Pension contributions (assuming from DC pensions)
        $pensionContributions = $user->dcPensions->sum(function ($pension) use ($user) {
            $annualSalary = $pension->annual_salary ?? $user->annual_employment_income ?? 0;
            $employeePercent = $pension->employee_contribution_percent ?? 0;

            return $annualSalary * ($employeePercent / 100);
        });

        // Living expenses (annualized)
        $livingExpenses = ($user->monthly_expenditure ?? 0) * 12;

        $outflows = [
            [
                'line_item' => 'Mortgage Payments',
                'category' => 'cash_outflow',
                'amount' => $mortgagePayments,
            ],
            [
                'line_item' => 'Property Expenses',
                'category' => 'cash_outflow',
                'amount' => $propertyExpenses,
            ],
            [
                'line_item' => 'Living Expenses',
                'category' => 'cash_outflow',
                'amount' => $livingExpenses,
            ],
            [
                'line_item' => 'Pension Contributions',
                'category' => 'cash_outflow',
                'amount' => $pensionContributions,
            ],
        ];

        $totalOutflows = collect($outflows)->sum('amount');

        $netCashflow = $totalInflows - $totalOutflows;

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'inflows' => $inflows,
            'total_inflows' => $totalInflows,
            'outflows' => $outflows,
            'total_outflows' => $totalOutflows,
            'net_cashflow' => $netCashflow,
        ];
    }

    /**
     * Calculate Balance Sheet for the user
     *
     * Balance Sheet: Assets - Liabilities = Equity
     */
    public function calculateBalanceSheet(User $user, Carbon $asOfDate): array
    {
        // Load all asset and liability relationships
        $user->load([
            'investmentAccounts',
            'properties',
            'businessInterests',
            'chattels',
            'dcPensions',
            'mortgages',
            'liabilities',
        ]);

        $assets = [];

        // Cash accounts - individual line items (include joint accounts where user is joint_owner)
        $cashAccounts = SavingsAccount::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->get();
        foreach ($cashAccounts as $account) {
            // For joint accounts, show user's share (50% for joint ownership)
            $amount = $account->current_balance;
            if ($account->ownership_type === 'joint' && $account->joint_owner_id) {
                $amount = $account->current_balance * 0.5;
            }
            $assets[] = [
                'line_item' => $account->institution ? "{$account->institution} - {$account->account_type}" : $account->account_type,
                'category' => 'cash',
                'amount' => $amount,
            ];
        }

        // Investment accounts - individual line items (include joint accounts)
        $investmentAccounts = \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->get();
        foreach ($investmentAccounts as $account) {
            // For joint accounts, show user's share (50% for joint ownership)
            $amount = $account->current_value;
            if ($account->ownership_type === 'joint' && $account->joint_owner_id) {
                $amount = $account->current_value * 0.5;
            }
            $assets[] = [
                'line_item' => $account->provider ? "{$account->provider} - {$account->account_type}" : $account->account_type,
                'category' => 'investment',
                'amount' => $amount,
            ];
        }

        // Properties - individual line items (include joint properties)
        $properties = \App\Models\Property::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->get();
        foreach ($properties as $property) {
            $propertyLabel = $property->address_line_1;
            if ($property->property_type) {
                $propertyLabel .= ' ('.str_replace('_', ' ', ucwords($property->property_type, '_')).')';
            }
            // For joint properties, show user's share (50% for joint ownership)
            $amount = $property->current_value;
            if ($property->ownership_type === 'joint' && $property->joint_owner_id) {
                $amount = $property->current_value * 0.5;
            }
            $assets[] = [
                'line_item' => $propertyLabel,
                'category' => 'property',
                'amount' => $amount,
            ];
        }

        // Business interests - individual line items
        // IMPORTANT: current_valuation is ALREADY stored as the user's share in the database
        // (divided by ownership_percentage when saving). No need to multiply again.
        foreach ($user->businessInterests as $business) {
            $assets[] = [
                'line_item' => $business->business_name ?? 'Business Interest',
                'category' => 'business',
                'amount' => $business->current_valuation,
            ];
        }

        // Chattels - individual line items
        // IMPORTANT: current_value is ALREADY stored as the user's share in the database
        // (divided by ownership_percentage when saving). No need to multiply again.
        foreach ($user->chattels as $chattel) {
            $assets[] = [
                'line_item' => $chattel->description ?? 'Chattel',
                'category' => 'chattel',
                'amount' => $chattel->current_value,
            ];
        }

        // Pensions - individual line items
        foreach ($user->dcPensions as $pension) {
            $assets[] = [
                'line_item' => $pension->provider ? "{$pension->provider} - DC Pension" : 'DC Pension',
                'category' => 'pension',
                'amount' => $pension->current_fund_value,
            ];
        }

        $totalAssets = collect($assets)->sum('amount');

        $liabilities = [];

        // Mortgages - individual line items (include joint mortgages)
        $mortgages = \App\Models\Mortgage::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->get();
        foreach ($mortgages as $mortgage) {
            // Include property address to ensure uniqueness when multiple mortgages have same lender
            $mortgageLabel = $mortgage->lender_name ?? 'Mortgage';

            // Try to get property address for this mortgage
            $property = $properties->firstWhere('id', $mortgage->property_id);
            if ($property && $property->address_line_1) {
                $mortgageLabel .= " ({$property->address_line_1})";
            } else {
                $mortgageLabel .= ' - Mortgage';
            }

            // For joint mortgages, show user's share (50% for joint ownership)
            $amount = $mortgage->outstanding_balance;
            if ($mortgage->ownership_type === 'joint' && $mortgage->joint_owner_id) {
                $amount = $mortgage->outstanding_balance * 0.5;
            }

            $liabilities[] = [
                'line_item' => $mortgageLabel,
                'category' => 'mortgage',
                'amount' => $amount,
            ];
        }

        // Other liabilities - individual line items (include joint liabilities)
        $userLiabilities = \App\Models\Estate\Liability::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->get();
        foreach ($userLiabilities as $liability) {
            $typeLabel = str_replace('_', ' ', ucwords($liability->liability_type, '_'));
            // For joint liabilities, show user's share (50% for joint ownership)
            $amount = $liability->current_balance;
            if ($liability->ownership_type === 'joint' && $liability->joint_owner_id) {
                $amount = $liability->current_balance * 0.5;
            }
            $liabilities[] = [
                'line_item' => $liability->liability_name ?? $typeLabel,
                'category' => 'liability',
                'amount' => $amount,
            ];
        }

        $totalLiabilities = collect($liabilities)->sum('amount');

        // Calculate equity
        $equity = $totalAssets - $totalLiabilities;

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => [
                [
                    'line_item' => 'Net Worth (Equity)',
                    'category' => 'equity',
                    'amount' => $equity,
                ],
            ],
            'total_equity' => $equity,
            'net_worth' => $equity,  // Alias for compatibility
        ];
    }
}

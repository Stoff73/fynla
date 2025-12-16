<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make all data columns nullable across the application.
 *
 * This migration allows users to save partial data without requiring all fields.
 * Only foreign key references (user_id, property_id, etc.) remain required.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Life Insurance Policies
        Schema::table('life_insurance_policies', function (Blueprint $table) {
            $table->string('provider')->nullable()->change();
            $table->decimal('sum_assured', 15, 2)->nullable()->change();
            $table->decimal('premium_amount', 10, 2)->nullable()->change();
            $table->date('policy_start_date')->nullable()->change();
            $table->integer('policy_term_years')->nullable()->change();
        });

        // Critical Illness Policies
        if (Schema::hasTable('critical_illness_policies')) {
            Schema::table('critical_illness_policies', function (Blueprint $table) {
                if (Schema::hasColumn('critical_illness_policies', 'provider')) {
                    $table->string('provider')->nullable()->change();
                }
                if (Schema::hasColumn('critical_illness_policies', 'sum_assured')) {
                    $table->decimal('sum_assured', 15, 2)->nullable()->change();
                }
                if (Schema::hasColumn('critical_illness_policies', 'premium_amount')) {
                    $table->decimal('premium_amount', 10, 2)->nullable()->change();
                }
                if (Schema::hasColumn('critical_illness_policies', 'policy_start_date')) {
                    $table->date('policy_start_date')->nullable()->change();
                }
                if (Schema::hasColumn('critical_illness_policies', 'policy_term_years')) {
                    $table->integer('policy_term_years')->nullable()->change();
                }
            });
        }

        // Income Protection Policies
        if (Schema::hasTable('income_protection_policies')) {
            Schema::table('income_protection_policies', function (Blueprint $table) {
                if (Schema::hasColumn('income_protection_policies', 'provider')) {
                    $table->string('provider')->nullable()->change();
                }
                if (Schema::hasColumn('income_protection_policies', 'monthly_benefit')) {
                    $table->decimal('monthly_benefit', 10, 2)->nullable()->change();
                }
                if (Schema::hasColumn('income_protection_policies', 'premium_amount')) {
                    $table->decimal('premium_amount', 10, 2)->nullable()->change();
                }
                if (Schema::hasColumn('income_protection_policies', 'policy_start_date')) {
                    $table->date('policy_start_date')->nullable()->change();
                }
            });
        }

        // Disability Policies
        if (Schema::hasTable('disability_policies')) {
            Schema::table('disability_policies', function (Blueprint $table) {
                if (Schema::hasColumn('disability_policies', 'provider')) {
                    $table->string('provider')->nullable()->change();
                }
                if (Schema::hasColumn('disability_policies', 'sum_assured')) {
                    $table->decimal('sum_assured', 15, 2)->nullable()->change();
                }
                if (Schema::hasColumn('disability_policies', 'premium_amount')) {
                    $table->decimal('premium_amount', 10, 2)->nullable()->change();
                }
                if (Schema::hasColumn('disability_policies', 'policy_start_date')) {
                    $table->date('policy_start_date')->nullable()->change();
                }
            });
        }

        // Sickness Illness Policies
        if (Schema::hasTable('sickness_illness_policies')) {
            Schema::table('sickness_illness_policies', function (Blueprint $table) {
                if (Schema::hasColumn('sickness_illness_policies', 'provider')) {
                    $table->string('provider')->nullable()->change();
                }
                if (Schema::hasColumn('sickness_illness_policies', 'sum_assured')) {
                    $table->decimal('sum_assured', 15, 2)->nullable()->change();
                }
                if (Schema::hasColumn('sickness_illness_policies', 'premium_amount')) {
                    $table->decimal('premium_amount', 10, 2)->nullable()->change();
                }
                if (Schema::hasColumn('sickness_illness_policies', 'policy_start_date')) {
                    $table->date('policy_start_date')->nullable()->change();
                }
            });
        }

        // Savings Accounts
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->string('account_type')->nullable()->change();
            $table->string('institution')->nullable()->change();
        });

        // Savings Goals
        if (Schema::hasTable('savings_goals')) {
            Schema::table('savings_goals', function (Blueprint $table) {
                if (Schema::hasColumn('savings_goals', 'goal_name')) {
                    $table->string('goal_name')->nullable()->change();
                }
                if (Schema::hasColumn('savings_goals', 'target_amount')) {
                    $table->decimal('target_amount', 15, 2)->nullable()->change();
                }
                if (Schema::hasColumn('savings_goals', 'target_date')) {
                    $table->date('target_date')->nullable()->change();
                }
            });
        }

        // DC Pensions
        Schema::table('dc_pensions', function (Blueprint $table) {
            // Note: scheme_name, scheme_type, provider may already be nullable from previous migrations
            // Using try-catch pattern with hasColumn checks
            if (! $this->isColumnNullable('dc_pensions', 'scheme_name')) {
                $table->string('scheme_name')->nullable()->change();
            }
            if (! $this->isColumnNullable('dc_pensions', 'scheme_type')) {
                $table->string('scheme_type')->nullable()->change();
            }
            if (! $this->isColumnNullable('dc_pensions', 'provider')) {
                $table->string('provider')->nullable()->change();
            }
        });

        // DB Pensions
        if (Schema::hasTable('db_pensions')) {
            Schema::table('db_pensions', function (Blueprint $table) {
                if (Schema::hasColumn('db_pensions', 'scheme_name')) {
                    $table->string('scheme_name')->nullable()->change();
                }
                if (Schema::hasColumn('db_pensions', 'accrual_rate')) {
                    $table->decimal('accrual_rate', 8, 4)->nullable()->change();
                }
                if (Schema::hasColumn('db_pensions', 'accrued_annual_pension')) {
                    $table->decimal('accrued_annual_pension', 15, 2)->nullable()->change();
                }
                if (Schema::hasColumn('db_pensions', 'normal_retirement_age')) {
                    $table->integer('normal_retirement_age')->nullable()->change();
                }
            });
        }

        // State Pensions
        if (Schema::hasTable('state_pensions')) {
            Schema::table('state_pensions', function (Blueprint $table) {
                if (Schema::hasColumn('state_pensions', 'qualifying_years')) {
                    $table->integer('qualifying_years')->nullable()->change();
                }
                if (Schema::hasColumn('state_pensions', 'forecast_weekly_amount')) {
                    $table->decimal('forecast_weekly_amount', 10, 2)->nullable()->change();
                }
            });
        }

        // Investment Accounts
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->string('account_type')->nullable()->change();
            $table->string('provider')->nullable()->change();
            $table->string('tax_year', 10)->nullable()->change();
        });

        // Investment Holdings
        if (Schema::hasTable('investment_holdings')) {
            Schema::table('investment_holdings', function (Blueprint $table) {
                if (Schema::hasColumn('investment_holdings', 'holding_name')) {
                    $table->string('holding_name')->nullable()->change();
                }
                if (Schema::hasColumn('investment_holdings', 'holding_type')) {
                    $table->string('holding_type')->nullable()->change();
                }
                if (Schema::hasColumn('investment_holdings', 'units')) {
                    $table->decimal('units', 20, 6)->nullable()->change();
                }
                if (Schema::hasColumn('investment_holdings', 'unit_price')) {
                    $table->decimal('unit_price', 15, 4)->nullable()->change();
                }
                if (Schema::hasColumn('investment_holdings', 'current_value')) {
                    $table->decimal('current_value', 15, 2)->nullable()->change();
                }
            });
        }

        // Properties
        Schema::table('properties', function (Blueprint $table) {
            $table->string('property_type')->nullable()->change();
            $table->string('address_line_1')->nullable()->change();
            $table->string('city')->nullable()->change();
            $table->string('postcode', 10)->nullable()->change();
            $table->date('purchase_date')->nullable()->change();
            $table->decimal('purchase_price', 15, 2)->nullable()->change();
            $table->decimal('current_value', 15, 2)->nullable()->change();
            $table->date('valuation_date')->nullable()->change();
        });

        // Mortgages
        Schema::table('mortgages', function (Blueprint $table) {
            if (Schema::hasColumn('mortgages', 'lender')) {
                $table->string('lender')->nullable()->change();
            }
            if (Schema::hasColumn('mortgages', 'original_amount')) {
                $table->decimal('original_amount', 15, 2)->nullable()->change();
            }
            if (Schema::hasColumn('mortgages', 'current_balance')) {
                $table->decimal('current_balance', 15, 2)->nullable()->change();
            }
            if (Schema::hasColumn('mortgages', 'interest_rate')) {
                $table->decimal('interest_rate', 8, 4)->nullable()->change();
            }
            if (Schema::hasColumn('mortgages', 'monthly_payment')) {
                $table->decimal('monthly_payment', 10, 2)->nullable()->change();
            }
            if (Schema::hasColumn('mortgages', 'start_date')) {
                $table->date('start_date')->nullable()->change();
            }
            if (Schema::hasColumn('mortgages', 'end_date')) {
                $table->date('end_date')->nullable()->change();
            }
        });

        // Family Members - keep relationship required but name optional
        Schema::table('family_members', function (Blueprint $table) {
            if (Schema::hasColumn('family_members', 'first_name')) {
                $table->string('first_name')->nullable()->change();
            }
            if (Schema::hasColumn('family_members', 'last_name')) {
                $table->string('last_name')->nullable()->change();
            }
            if (Schema::hasColumn('family_members', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->change();
            }
        });

        // Liabilities
        if (Schema::hasTable('liabilities')) {
            Schema::table('liabilities', function (Blueprint $table) {
                if (Schema::hasColumn('liabilities', 'liability_type')) {
                    $table->string('liability_type')->nullable()->change();
                }
                if (Schema::hasColumn('liabilities', 'liability_name')) {
                    $table->string('liability_name')->nullable()->change();
                }
                if (Schema::hasColumn('liabilities', 'current_balance')) {
                    $table->decimal('current_balance', 15, 2)->nullable()->change();
                }
                if (Schema::hasColumn('liabilities', 'interest_rate')) {
                    $table->decimal('interest_rate', 8, 4)->nullable()->change();
                }
                if (Schema::hasColumn('liabilities', 'monthly_payment')) {
                    $table->decimal('monthly_payment', 10, 2)->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: This is a destructive operation - reverting will fail if NULL data exists.
     * Consider not reverting or handling existing NULL values before reverting.
     */
    public function down(): void
    {
        // We intentionally don't revert these changes as:
        // 1. Data may now contain NULLs which would cause errors
        // 2. The application should support nullable fields going forward
        // If you need to revert, manually update each table after ensuring no NULLs exist
    }

    /**
     * Check if a column is already nullable.
     */
    private function isColumnNullable(string $table, string $column): bool
    {
        $columnInfo = \DB::select("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column]);

        if (empty($columnInfo)) {
            return true; // Column doesn't exist, treat as handled
        }

        return $columnInfo[0]->Null === 'YES';
    }
};

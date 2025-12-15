<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDataMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:verify {--detailed : Show detailed verification results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify data migration integrity and completeness';

    /**
     * Verification results
     */
    private array $results = [];

    private int $totalIssues = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $detailed = $this->option('detailed');

        $this->info('========================================');
        $this->info('Data Migration Verification');
        $this->info('========================================');
        $this->newLine();

        // Run all verification checks
        $this->verifyAssetsMigration();
        $this->verifySavingsMigration();
        $this->verifyDataIntegrity();
        $this->verifyUserAssociations();
        $this->verifyNoDuplicates();

        // Display results
        $this->displayResults($detailed);

        return $this->totalIssues === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Verify assets migration
     */
    private function verifyAssetsMigration(): void
    {
        $this->info('🔍 Verifying assets migration...');

        if (! DB::getSchemaBuilder()->hasTable('assets')) {
            $this->results['assets'] = [
                'status' => 'warning',
                'message' => 'Old "assets" table not found',
                'old_count' => 0,
                'new_count' => 0,
            ];

            return;
        }

        $oldAssets = DB::table('assets')->count();
        $newProperties = DB::table('properties')->count();
        $newBusinesses = DB::table('business_interests')->count();
        $newChattels = DB::table('chattels')->count();
        $newTotal = $newProperties + $newBusinesses + $newChattels;

        // Count pensions and investments that should be skipped
        $pensionsSkipped = DB::table('assets')->where('asset_type', 'pension')->count();
        $investmentsSkipped = DB::table('assets')->where('asset_type', 'investment')->count();
        $expectedTotal = $oldAssets - $pensionsSkipped - $investmentsSkipped;

        $status = ($newTotal === $expectedTotal) ? 'success' : 'error';
        if ($newTotal !== $expectedTotal) {
            $this->totalIssues++;
        }

        $this->results['assets'] = [
            'status' => $status,
            'message' => 'Assets migration verification',
            'old_count' => $oldAssets,
            'expected_new' => $expectedTotal,
            'new_count' => $newTotal,
            'properties' => $newProperties,
            'businesses' => $newBusinesses,
            'chattels' => $newChattels,
            'pensions_skipped' => $pensionsSkipped,
            'investments_skipped' => $investmentsSkipped,
        ];
    }

    /**
     * Verify savings accounts migration
     */
    private function verifySavingsMigration(): void
    {
        $this->info('🔍 Verifying savings accounts migration...');

        if (! DB::getSchemaBuilder()->hasTable('savings_accounts')) {
            $this->results['savings'] = [
                'status' => 'warning',
                'message' => 'Old "savings_accounts" table not found',
                'old_count' => 0,
                'new_count' => 0,
            ];

            return;
        }

        $oldSavings = DB::table('savings_accounts')->count();
        $newCashAccounts = DB::table('cash_accounts')->count();

        $status = ($oldSavings === $newCashAccounts) ? 'success' : 'error';
        if ($oldSavings !== $newCashAccounts) {
            $this->totalIssues++;
        }

        $this->results['savings'] = [
            'status' => $status,
            'message' => 'Savings accounts migration verification',
            'old_count' => $oldSavings,
            'new_count' => $newCashAccounts,
        ];
    }

    /**
     * Verify data integrity (foreign keys, nulls, etc.)
     */
    private function verifyDataIntegrity(): void
    {
        $this->info('🔍 Verifying data integrity...');

        $issues = [];

        // Check properties
        $invalidProperties = DB::table('properties')
            ->whereNull('user_id')
            ->orWhereNull('current_value')
            ->count();

        if ($invalidProperties > 0) {
            $issues[] = "{$invalidProperties} properties with null user_id or current_value";
            $this->totalIssues++;
        }

        // Check business interests
        $invalidBusinesses = DB::table('business_interests')
            ->whereNull('user_id')
            ->orWhereNull('current_valuation')
            ->count();

        if ($invalidBusinesses > 0) {
            $issues[] = "{$invalidBusinesses} business interests with null user_id or current_valuation";
            $this->totalIssues++;
        }

        // Check chattels
        $invalidChattels = DB::table('chattels')
            ->whereNull('user_id')
            ->orWhereNull('current_value')
            ->count();

        if ($invalidChattels > 0) {
            $issues[] = "{$invalidChattels} chattels with null user_id or current_value";
            $this->totalIssues++;
        }

        // Check cash accounts
        $invalidCash = DB::table('cash_accounts')
            ->whereNull('user_id')
            ->orWhereNull('current_balance')
            ->count();

        if ($invalidCash > 0) {
            $issues[] = "{$invalidCash} cash accounts with null user_id or current_balance";
            $this->totalIssues++;
        }

        $this->results['integrity'] = [
            'status' => count($issues) === 0 ? 'success' : 'error',
            'message' => 'Data integrity verification',
            'issues' => $issues,
        ];
    }

    /**
     * Verify user_id associations are valid
     */
    private function verifyUserAssociations(): void
    {
        $this->info('🔍 Verifying user associations...');

        $issues = [];

        // Check properties
        $orphanedProperties = DB::table('properties')
            ->leftJoin('users', 'properties.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->count();

        if ($orphanedProperties > 0) {
            $issues[] = "{$orphanedProperties} properties with invalid user_id";
            $this->totalIssues++;
        }

        // Check business interests
        $orphanedBusinesses = DB::table('business_interests')
            ->leftJoin('users', 'business_interests.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->count();

        if ($orphanedBusinesses > 0) {
            $issues[] = "{$orphanedBusinesses} business interests with invalid user_id";
            $this->totalIssues++;
        }

        // Check chattels
        $orphanedChattels = DB::table('chattels')
            ->leftJoin('users', 'chattels.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->count();

        if ($orphanedChattels > 0) {
            $issues[] = "{$orphanedChattels} chattels with invalid user_id";
            $this->totalIssues++;
        }

        // Check cash accounts
        $orphanedCash = DB::table('cash_accounts')
            ->leftJoin('users', 'cash_accounts.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->count();

        if ($orphanedCash > 0) {
            $issues[] = "{$orphanedCash} cash accounts with invalid user_id";
            $this->totalIssues++;
        }

        $this->results['associations'] = [
            'status' => count($issues) === 0 ? 'success' : 'error',
            'message' => 'User association verification',
            'issues' => $issues,
        ];
    }

    /**
     * Check for duplicate records
     */
    private function verifyNoDuplicates(): void
    {
        $this->info('🔍 Checking for duplicate records...');

        $issues = [];

        // Check for duplicate properties (same user, same address)
        $duplicateProperties = DB::table('properties')
            ->select('user_id', 'address_line_1', DB::raw('COUNT(*) as count'))
            ->groupBy('user_id', 'address_line_1')
            ->having('count', '>', 1)
            ->count();

        if ($duplicateProperties > 0) {
            $issues[] = "{$duplicateProperties} potential duplicate properties";
            $this->totalIssues++;
        }

        // Check for duplicate cash accounts (same user, same account number)
        $duplicateCash = DB::table('cash_accounts')
            ->whereNotNull('account_number')
            ->select('user_id', 'account_number', DB::raw('COUNT(*) as count'))
            ->groupBy('user_id', 'account_number')
            ->having('count', '>', 1)
            ->count();

        if ($duplicateCash > 0) {
            $issues[] = "{$duplicateCash} potential duplicate cash accounts";
            $this->totalIssues++;
        }

        $this->results['duplicates'] = [
            'status' => count($issues) === 0 ? 'success' : 'warning',
            'message' => 'Duplicate records check',
            'issues' => $issues,
        ];
    }

    /**
     * Display verification results
     */
    private function displayResults(bool $detailed): void
    {
        $this->newLine();
        $this->info('========================================');
        $this->info('Verification Results');
        $this->info('========================================');
        $this->newLine();

        foreach ($this->results as $key => $result) {
            $icon = match ($result['status']) {
                'success' => '✅',
                'warning' => '⚠️ ',
                'error' => '❌',
                default => '❓',
            };

            $this->line("{$icon} {$result['message']}");

            if ($detailed) {
                if (isset($result['old_count']) && isset($result['new_count'])) {
                    $this->line("   Old records: {$result['old_count']}");
                    if (isset($result['expected_new'])) {
                        $this->line("   Expected: {$result['expected_new']}");
                    }
                    $this->line("   New records: {$result['new_count']}");
                }

                if (isset($result['properties'])) {
                    $this->line("   - Properties: {$result['properties']}");
                    $this->line("   - Businesses: {$result['businesses']}");
                    $this->line("   - Chattels: {$result['chattels']}");
                    $this->line("   - Pensions skipped: {$result['pensions_skipped']}");
                    $this->line("   - Investments skipped: {$result['investments_skipped']}");
                }

                if (isset($result['issues']) && count($result['issues']) > 0) {
                    foreach ($result['issues'] as $issue) {
                        $this->line("   - {$issue}");
                    }
                }
            }
            $this->newLine();
        }

        if ($this->totalIssues === 0) {
            $this->info('✅ All verification checks passed!');
        } else {
            $this->error("❌ Found {$this->totalIssues} issue(s) that need attention");
            $this->newLine();
            $this->warn('Run with --detailed flag for more information');
        }
    }
}

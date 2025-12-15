<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CashAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSavingsToCash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:savings-to-cash {--dry-run : Run migration without committing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy savings_accounts to new cash_accounts table';

    /**
     * Migration statistics
     */
    private array $stats = [
        'total_accounts' => 0,
        'accounts_migrated' => 0,
        'isa_accounts' => 0,
        'regular_accounts' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('========================================');
        $this->info('Savings to Cash Accounts Migration');
        $this->info('========================================');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be committed');
            $this->newLine();
        }

        // Check if old savings_accounts table exists
        if (! DB::getSchemaBuilder()->hasTable('savings_accounts')) {
            $this->error('❌ Table "savings_accounts" not found. Migration cannot proceed.');

            return Command::FAILURE;
        }

        // Count total accounts to migrate
        $this->stats['total_accounts'] = DB::table('savings_accounts')->count();

        if ($this->stats['total_accounts'] === 0) {
            $this->info('✅ No savings accounts found to migrate.');

            return Command::SUCCESS;
        }

        $this->info("Found {$this->stats['total_accounts']} savings accounts to migrate");
        $this->newLine();

        if (! $isDryRun && ! $this->confirm('Do you want to proceed with the migration?', true)) {
            $this->warn('Migration cancelled by user.');

            return Command::FAILURE;
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            $this->migrateSavingsAccounts();

            if ($isDryRun) {
                DB::rollBack();
                $this->warn('🔄 DRY RUN - Transaction rolled back');
            } else {
                DB::commit();
                $this->info('✅ Transaction committed successfully');
            }

            $this->newLine();
            $this->displayStatistics();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Migration failed: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Migrate savings accounts from old table to cash_accounts
     */
    private function migrateSavingsAccounts(): void
    {
        $accounts = DB::table('savings_accounts')->get();

        $progressBar = $this->output->createProgressBar($accounts->count());
        $progressBar->start();

        foreach ($accounts as $account) {
            try {
                $this->migrateSavingsAccount($account);
                $this->stats['accounts_migrated']++;

                if ($account->is_isa) {
                    $this->stats['isa_accounts']++;
                } else {
                    $this->stats['regular_accounts']++;
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->error("\nError migrating savings account ID {$account->id}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Migrate a single savings account to cash_accounts table
     */
    private function migrateSavingsAccount($account): void
    {
        // Map account type
        $accountType = $this->mapAccountType($account);

        // Map purpose based on account type and ISA status
        $purpose = $this->mapPurpose($account);

        CashAccount::create([
            'user_id' => $account->user_id,
            'household_id' => null, // Will be set when household structure is created
            'trust_id' => null,
            'account_name' => $this->generateAccountName($account),
            'account_type' => $accountType,
            'institution' => $account->institution,
            'account_number' => $account->account_number,
            'current_balance' => $account->current_balance,
            'interest_rate' => $account->interest_rate,
            'access_type' => $account->access_type,
            'notice_period_days' => $account->notice_period_days,
            'maturity_date' => $account->maturity_date,
            'purpose' => $purpose,
            'ownership_type' => 'individual', // Default, can be updated manually
            'ownership_percentage' => 100,
            'is_joint' => false,
            'joint_owner_name' => null,
            'isa_subscription_year' => $account->isa_subscription_year,
            'isa_subscription_amount' => $account->isa_subscription_amount,
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
        ]);
    }

    /**
     * Map old account_type to new account_type enum
     */
    private function mapAccountType($account): string
    {
        if ($account->is_isa) {
            return 'cash_isa';
        }

        // Map based on account_type field from old table
        return match (strtolower($account->account_type ?? 'savings_account')) {
            'savings', 'savings_account' => 'savings_account',
            'current', 'current_account' => 'current_account',
            'fixed', 'fixed_deposit' => 'fixed_deposit',
            'notice' => 'notice_account',
            default => 'savings_account',
        };
    }

    /**
     * Map account purpose based on type and ISA status
     */
    private function mapPurpose($account): string
    {
        if ($account->is_isa) {
            return 'savings'; // ISAs are typically for savings
        }

        // Map based on access type
        return match ($account->access_type) {
            'immediate' => 'emergency_fund', // Immediate access suggests emergency fund
            'notice', 'fixed' => 'savings', // Notice/fixed suggests savings goal
            default => 'general',
        };
    }

    /**
     * Generate a descriptive account name
     */
    private function generateAccountName($account): string
    {
        $name = $account->institution;

        if ($account->is_isa) {
            $name .= ' Cash ISA';
        } else {
            $name .= ' '.ucfirst(str_replace('_', ' ', $account->account_type ?? 'Savings Account'));
        }

        return $name;
    }

    /**
     * Display migration statistics
     */
    private function displayStatistics(): void
    {
        $this->info('========================================');
        $this->info('Migration Statistics');
        $this->info('========================================');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Accounts', $this->stats['total_accounts']],
                ['Accounts Migrated', $this->stats['accounts_migrated']],
                ['ISA Accounts', $this->stats['isa_accounts']],
                ['Regular Accounts', $this->stats['regular_accounts']],
                ['Errors', $this->stats['errors']],
            ]
        );

        if ($this->stats['errors'] === 0 && $this->stats['accounts_migrated'] > 0) {
            $this->info("\n✅ Migration completed successfully!");
        } elseif ($this->stats['errors'] > 0) {
            $this->warn("\n⚠️  Migration completed with {$this->stats['errors']} errors");
        }
    }
}

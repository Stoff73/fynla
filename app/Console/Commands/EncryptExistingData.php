<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EncryptExistingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:encrypt
                            {--model= : Specific model to encrypt (e.g., User, SavingsAccount)}
                            {--batch=100 : Number of records to process per batch}
                            {--dry-run : Show what would be encrypted without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing unencrypted data in financial models';

    /**
     * Models and their sensitive fields to encrypt.
     */
    private array $modelsToEncrypt = [
        'User' => [
            'class' => \App\Models\User::class,
            'fields' => [
                'annual_employment_income',
                'annual_self_employment_income',
                'annual_rental_income',
                'annual_dividend_income',
                'annual_interest_income',
                'annual_other_income',
                'annual_trust_income',
            ],
        ],
        'CashAccount' => [
            'class' => \App\Models\CashAccount::class,
            'fields' => ['account_number', 'sort_code'],
        ],
        'SavingsAccount' => [
            'class' => \App\Models\SavingsAccount::class,
            'fields' => ['current_balance'],
        ],
        'InvestmentAccount' => [
            'class' => \App\Models\Investment\InvestmentAccount::class,
            'fields' => ['current_value', 'account_number'],
        ],
        'FamilyMember' => [
            'class' => \App\Models\FamilyMember::class,
            'fields' => ['national_insurance_number'],
        ],
        'DCPension' => [
            'class' => \App\Models\DCPension::class,
            'fields' => ['current_fund_value', 'monthly_contribution_amount', 'employer_contribution_amount'],
        ],
        'DBPension' => [
            'class' => \App\Models\DBPension::class,
            'fields' => ['accrued_annual_pension', 'lump_sum_entitlement'],
        ],
        'StatePension' => [
            'class' => \App\Models\StatePension::class,
            'fields' => ['current_annual_amount', 'forecast_full_amount'],
        ],
        'Property' => [
            'class' => \App\Models\Property::class,
            'fields' => ['current_value', 'purchase_price'],
        ],
        'Mortgage' => [
            'class' => \App\Models\Mortgage::class,
            'fields' => ['current_balance', 'original_amount', 'monthly_payment', 'mortgage_account_number'],
        ],
        'Liability' => [
            'class' => \App\Models\Estate\Liability::class,
            'fields' => ['current_balance', 'original_amount', 'monthly_payment'],
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificModel = $this->option('model');
        $batchSize = (int) $this->option('batch');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting data encryption process...');
        $this->newLine();

        $modelsToProcess = $specificModel
            ? [$specificModel => $this->modelsToEncrypt[$specificModel] ?? null]
            : $this->modelsToEncrypt;

        if ($specificModel && ! isset($this->modelsToEncrypt[$specificModel])) {
            $this->error("Unknown model: {$specificModel}");
            $this->info('Available models: '.implode(', ', array_keys($this->modelsToEncrypt)));

            return Command::FAILURE;
        }

        $totalEncrypted = 0;

        foreach ($modelsToProcess as $modelName => $config) {
            if (! $config) {
                continue;
            }

            $this->info("Processing {$modelName}...");

            $encrypted = $this->encryptModel($config['class'], $config['fields'], $batchSize, $dryRun);
            $totalEncrypted += $encrypted;

            $this->info("  Encrypted {$encrypted} record(s)");
            $this->newLine();
        }

        $this->newLine();
        $this->info("Encryption complete. Total records processed: {$totalEncrypted}");

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }

    /**
     * Encrypt data for a specific model.
     */
    private function encryptModel(string $modelClass, array $fields, int $batchSize, bool $dryRun): int
    {
        $encryptedCount = 0;

        $modelClass::query()
            ->chunkById($batchSize, function ($records) use ($fields, $dryRun, &$encryptedCount) {
                foreach ($records as $record) {
                    $needsUpdate = false;

                    foreach ($fields as $field) {
                        $value = $record->getRawOriginal($field);

                        // Skip if already encrypted (starts with eyJ which is base64 JSON)
                        if ($value !== null && ! $this->isLikelyEncrypted($value)) {
                            $needsUpdate = true;
                        }
                    }

                    if ($needsUpdate && ! $dryRun) {
                        // Simply saving the record will trigger the encryption casts
                        // We need to set each field individually to trigger the mutator
                        foreach ($fields as $field) {
                            $rawValue = $record->getRawOriginal($field);
                            if ($rawValue !== null && ! $this->isLikelyEncrypted($rawValue)) {
                                $record->{$field} = $rawValue;
                            }
                        }
                        $record->saveQuietly();
                    }

                    if ($needsUpdate) {
                        $encryptedCount++;
                    }
                }
            });

        return $encryptedCount;
    }

    /**
     * Check if a value appears to already be encrypted.
     * Encrypted values are base64 JSON strings starting with eyJ
     */
    private function isLikelyEncrypted(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // Laravel encrypted strings start with eyJ (base64 encoded JSON)
        return str_starts_with($value, 'eyJ');
    }
}

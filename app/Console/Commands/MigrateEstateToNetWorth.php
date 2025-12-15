<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateEstateToNetWorth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:estate-to-networth {--dry-run : Run migration without committing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy estate assets to new NetWorth module tables (properties, business_interests, chattels)';

    /**
     * Migration statistics
     */
    private array $stats = [
        'total_assets' => 0,
        'properties_migrated' => 0,
        'businesses_migrated' => 0,
        'chattels_migrated' => 0,
        'pensions_skipped' => 0,
        'investments_skipped' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('========================================');
        $this->info('Estate to Net Worth Migration');
        $this->info('========================================');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be committed');
            $this->newLine();
        }

        // Check if old assets table exists
        if (! DB::getSchemaBuilder()->hasTable('assets')) {
            $this->error('❌ Table "assets" not found. Migration cannot proceed.');

            return Command::FAILURE;
        }

        // Count total assets to migrate
        $this->stats['total_assets'] = DB::table('assets')->count();

        if ($this->stats['total_assets'] === 0) {
            $this->info('✅ No assets found to migrate.');

            return Command::SUCCESS;
        }

        $this->info("Found {$this->stats['total_assets']} assets to migrate");
        $this->newLine();

        if (! $isDryRun && ! $this->confirm('Do you want to proceed with the migration?', true)) {
            $this->warn('Migration cancelled by user.');

            return Command::FAILURE;
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            $this->migrateAssets();

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
     * Migrate assets from old table to new tables
     */
    private function migrateAssets(): void
    {
        $assets = DB::table('assets')->get();

        $progressBar = $this->output->createProgressBar($assets->count());
        $progressBar->start();

        foreach ($assets as $asset) {
            try {
                switch ($asset->asset_type) {
                    case 'property':
                        $this->migrateProperty($asset);
                        $this->stats['properties_migrated']++;
                        break;

                    case 'business':
                        $this->migrateBusiness($asset);
                        $this->stats['businesses_migrated']++;
                        break;

                    case 'pension':
                        // Pensions are managed in Retirement module, skip
                        $this->stats['pensions_skipped']++;
                        break;

                    case 'investment':
                        // Investments are managed in Investment module, skip
                        $this->stats['investments_skipped']++;
                        break;

                    case 'other':
                    default:
                        $this->migrateChattel($asset);
                        $this->stats['chattels_migrated']++;
                        break;
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->error("\nError migrating asset ID {$asset->id}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Migrate property asset to properties table
     */
    private function migrateProperty($asset): void
    {
        Property::create([
            'user_id' => $asset->user_id,
            'household_id' => null, // Will be set when household structure is created
            'trust_id' => null,
            'property_type' => 'residential', // Default, can be updated manually
            'address_line_1' => $asset->asset_name,
            'address_line_2' => null,
            'city' => null,
            'county' => null,
            'postcode' => null,
            'current_valuation' => $asset->current_valuation,
            'purchase_price' => $asset->current_valuation, // Assume same as current value
            'purchase_date' => $asset->valuation_date,
            'valuation_date' => $asset->valuation_date,
            'ownership_type' => $asset->ownership_type === 'individual' ? 'individual' : 'joint',
            'ownership_percentage' => $asset->ownership_type === 'individual' ? 100 : 50, // Assume 50/50 for joint
            'is_main_residence' => false, // Will be updated manually if needed
            'is_iht_exempt' => $asset->is_iht_exempt,
            'exemption_reason' => $asset->exemption_reason,
            'created_at' => $asset->created_at,
            'updated_at' => $asset->updated_at,
        ]);
    }

    /**
     * Migrate business asset to business_interests table
     */
    private function migrateBusiness($asset): void
    {
        BusinessInterest::create([
            'user_id' => $asset->user_id,
            'household_id' => null,
            'trust_id' => null,
            'business_name' => $asset->asset_name,
            'business_type' => 'other', // Default
            'ownership_percentage' => $asset->ownership_type === 'individual' ? 100 : 50,
            'current_valuation' => $asset->current_valuation,
            'valuation_date' => $asset->valuation_date,
            'ownership_type' => $asset->ownership_type === 'individual' ? 'individual' : 'joint',
            'is_trading' => true, // Assume trading
            'is_iht_exempt' => $asset->is_iht_exempt,
            'exemption_reason' => $asset->exemption_reason,
            'created_at' => $asset->created_at,
            'updated_at' => $asset->updated_at,
        ]);
    }

    /**
     * Migrate other/chattel asset to chattels table
     */
    private function migrateChattel($asset): void
    {
        Chattel::create([
            'user_id' => $asset->user_id,
            'household_id' => null,
            'trust_id' => null,
            'item_name' => $asset->asset_name,
            'category' => 'other', // Default
            'current_valuation' => $asset->current_valuation,
            'acquisition_cost' => $asset->current_valuation,
            'acquisition_date' => $asset->valuation_date,
            'valuation_date' => $asset->valuation_date,
            'ownership_type' => $asset->ownership_type === 'individual' ? 'individual' : 'joint',
            'ownership_percentage' => $asset->ownership_type === 'individual' ? 100 : 50,
            'is_set' => false,
            'set_value' => null,
            'is_iht_exempt' => $asset->is_iht_exempt,
            'exemption_reason' => $asset->exemption_reason,
            'created_at' => $asset->created_at,
            'updated_at' => $asset->updated_at,
        ]);
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
                ['Total Assets', $this->stats['total_assets']],
                ['Properties Migrated', $this->stats['properties_migrated']],
                ['Businesses Migrated', $this->stats['businesses_migrated']],
                ['Chattels Migrated', $this->stats['chattels_migrated']],
                ['Pensions Skipped', $this->stats['pensions_skipped']],
                ['Investments Skipped', $this->stats['investments_skipped']],
                ['Errors', $this->stats['errors']],
            ]
        );

        $successfulMigrations = $this->stats['properties_migrated']
            + $this->stats['businesses_migrated']
            + $this->stats['chattels_migrated'];

        if ($this->stats['errors'] === 0 && $successfulMigrations > 0) {
            $this->info("\n✅ Migration completed successfully!");
        } elseif ($this->stats['errors'] > 0) {
            $this->warn("\n⚠️  Migration completed with {$this->stats['errors']} errors");
        }
    }
}

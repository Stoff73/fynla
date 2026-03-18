<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Liability;
use App\Models\FamilyMember;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPreviewData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preview:reset {persona? : The persona ID to reset (young_family, peak_earners, entrepreneur, young_saver, retired_couple, student). If omitted, resets all.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset preview user data to original state from persona JSON files';

    /**
     * Valid persona IDs.
     */
    private const VALID_PERSONAS = [
        'young_family',
        'peak_earners',
        'entrepreneur',
        'young_saver',
        'retired_couple',
        'student',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $persona = $this->argument('persona');

        if ($persona && ! in_array($persona, self::VALID_PERSONAS)) {
            $this->error("Invalid persona ID: {$persona}");
            $this->info('Valid personas: '.implode(', ', self::VALID_PERSONAS));

            return Command::FAILURE;
        }

        $personas = $persona ? [$persona] : self::VALID_PERSONAS;

        $this->info('Resetting preview data...');

        foreach ($personas as $personaId) {
            $this->resetPersona($personaId);
        }

        $this->info('Preview data reset complete!');

        return Command::SUCCESS;
    }

    /**
     * Reset a single persona's data.
     */
    private function resetPersona(string $personaId): void
    {
        $this->info("Resetting persona: {$personaId}");

        // Find the preview user
        $user = User::where('is_preview_user', true)
            ->where('preview_persona_id', $personaId)
            ->first();

        if (! $user) {
            $this->warn("  Preview user not found for {$personaId}. Run 'php artisan db:seed --class=PreviewUserSeeder' first.");

            return;
        }

        // Find spouse if exists
        $spouse = User::where('is_preview_user', true)
            ->where('preview_persona_id', "{$personaId}_spouse")
            ->first();

        DB::transaction(function () use ($user, $spouse) {
            // Delete all existing data for this user (and spouse if exists)
            $this->deleteUserData($user);
            if ($spouse) {
                $this->deleteUserData($spouse);
                // Delete spouse user
                $spouse->tokens()->delete();
                $spouse->delete();
            }

            // Reset user's spouse_id
            $user->spouse_id = null;
            $user->save();

            // Delete the user
            $user->tokens()->delete();
            $user->delete();
        });

        // Re-run the seeder for this persona
        $seeder = new \Database\Seeders\PreviewUserSeeder;
        $seeder->setCommand($this);

        // Create a temporary seeder that only seeds this persona
        $this->seedSinglePersona($personaId);

        $this->info("  Reset complete for {$personaId}");
    }

    /**
     * Delete all financial data for a user.
     */
    private function deleteUserData(User $user): void
    {
        // Delete in reverse order of dependencies
        Holding::whereHasMorph('holdable', [InvestmentAccount::class], function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->delete();

        Holding::whereHasMorph('holdable', [DCPension::class], function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->delete();

        InvestmentAccount::where('user_id', $user->id)->delete();
        SavingsAccount::where('user_id', $user->id)->delete();
        DCPension::where('user_id', $user->id)->delete();
        DBPension::where('user_id', $user->id)->delete();
        LifeInsurancePolicy::where('user_id', $user->id)->delete();
        CriticalIllnessPolicy::where('user_id', $user->id)->delete();
        IncomeProtectionPolicy::where('user_id', $user->id)->delete();
        Liability::where('user_id', $user->id)->delete();
        FamilyMember::where('user_id', $user->id)->delete();
        Mortgage::where('user_id', $user->id)->delete();
        Property::where('user_id', $user->id)->delete();
    }

    /**
     * Seed a single persona using the PreviewUserSeeder logic.
     */
    private function seedSinglePersona(string $personaId): void
    {
        // Call the db:seed command for just this persona
        // We'll use a fresh seeder instance
        $this->call('db:seed', [
            '--class' => 'PreviewUserSeeder',
        ]);

        // Note: The seeder will skip personas that already exist,
        // and only create the one we just deleted
    }
}

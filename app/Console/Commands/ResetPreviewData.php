<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\ExpenditureProfile;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\ProtectionProfile;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\PreviewUserSeeder;
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
                // Hard-delete spouse user — User model uses SoftDeletes, but
                // PreviewUserSeeder's lookup excludes soft-deleted rows, which
                // would leak the email and break the next reseed.
                $spouse->tokens()->delete();
                $spouse->forceDelete();
            }

            // Reset user's spouse_id
            $user->spouse_id = null;
            $user->save();

            // Hard-delete the user (see SoftDeletes note above).
            $user->tokens()->delete();
            $user->forceDelete();
        });

        // Re-run the seeder for this persona
        $seeder = new PreviewUserSeeder;
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
        // Hard-delete every child row. Most child models use SoftDeletes,
        // so plain ->delete() would only set deleted_at and the FK would
        // still reference the row — blocking the user's forceDelete.
        // We explicit-delete (rather than relying on cascade) to (a) make
        // the persona-touched surface visible and lockable via
        // PreviewResetCompletenessTest, (b) cover any FKs that don't
        // cascade, and (c) keep behaviour consistent with
        // PreviewUserSeeder::deletePreviewUser.

        Holding::whereHasMorph('holdable', [InvestmentAccount::class], function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->forceDelete();

        Holding::whereHasMorph('holdable', [DCPension::class], function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->forceDelete();

        // AI conversation messages first, then conversations.
        AiMessage::whereIn(
            'conversation_id',
            AiConversation::where('user_id', $user->id)->pluck('id')
        )->forceDelete();
        AiConversation::where('user_id', $user->id)->forceDelete();

        // Module child entities.
        InvestmentAccount::where('user_id', $user->id)->forceDelete();
        SavingsAccount::where('user_id', $user->id)->forceDelete();
        DCPension::where('user_id', $user->id)->forceDelete();
        DBPension::where('user_id', $user->id)->forceDelete();
        LifeInsurancePolicy::where('user_id', $user->id)->forceDelete();
        CriticalIllnessPolicy::where('user_id', $user->id)->forceDelete();
        IncomeProtectionPolicy::where('user_id', $user->id)->forceDelete();
        Liability::where('user_id', $user->id)->forceDelete();
        FamilyMember::where('user_id', $user->id)->forceDelete();
        Mortgage::where('user_id', $user->id)->forceDelete();
        Property::where('user_id', $user->id)->forceDelete();

        // Profiles (added 2026-04-27 — eval HTTP rewrite plan §8.2).
        ProtectionProfile::where('user_id', $user->id)->forceDelete();
        RetirementProfile::where('user_id', $user->id)->forceDelete();
        IHTProfile::where('user_id', $user->id)->forceDelete();
        ExpenditureProfile::where('user_id', $user->id)->forceDelete();

        // Goals + life events.
        Goal::where('user_id', $user->id)->forceDelete();
        LifeEvent::where('user_id', $user->id)->forceDelete();

        // Estate documents.
        LastingPowerOfAttorney::where('user_id', $user->id)->forceDelete();
        Will::where('user_id', $user->id)->forceDelete();
        Trust::where('user_id', $user->id)->forceDelete();
        Gift::where('user_id', $user->id)->forceDelete();
        Chattel::where('user_id', $user->id)->forceDelete();
        BusinessInterest::where('user_id', $user->id)->forceDelete();
        Asset::where('user_id', $user->id)->forceDelete();
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

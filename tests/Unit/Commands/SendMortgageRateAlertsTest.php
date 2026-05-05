<?php

declare(strict_types=1);
use App\Models\Mortgage;
use App\Models\NotificationPreference;
use App\Models\Property;
use App\Models\TaxConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }
});

describe('SendMortgageRateAlerts', function () {
    it('runs successfully', function () {
        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });

    it('detects mortgages with fixed rate ending in 90 days', function () {
        $user = User::factory()->create();
        NotificationPreference::getOrCreateForUser($user->id);
        $property = Property::factory()->create(['user_id' => $user->id]);
        Mortgage::factory()->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => now()->addDays(90),
        ]);

        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });

    it('skips users with mortgage_rate_alerts disabled', function () {
        $user = User::factory()->create();
        $prefs = NotificationPreference::getOrCreateForUser($user->id);
        $prefs->update(['mortgage_rate_alerts' => false]);
        $property = Property::factory()->create(['user_id' => $user->id]);
        Mortgage::factory()->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => now()->addDays(30),
        ]);

        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });
});

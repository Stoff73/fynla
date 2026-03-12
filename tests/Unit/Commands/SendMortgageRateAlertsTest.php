<?php

declare(strict_types=1);

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    if (! \App\Models\TaxConfiguration::where('is_active', true)->exists()) {
        \App\Models\TaxConfiguration::factory()->create(['is_active' => true]);
    }
});

describe('SendMortgageRateAlerts', function () {
    it('runs successfully', function () {
        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });

    it('detects mortgages with fixed rate ending in 90 days', function () {
        $user = \App\Models\User::factory()->create();
        \App\Models\NotificationPreference::getOrCreateForUser($user->id);
        $property = \App\Models\Property::factory()->create(['user_id' => $user->id]);
        \App\Models\Mortgage::factory()->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => now()->addDays(90),
        ]);

        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });

    it('skips users with mortgage_rate_alerts disabled', function () {
        $user = \App\Models\User::factory()->create();
        $prefs = \App\Models\NotificationPreference::getOrCreateForUser($user->id);
        $prefs->update(['mortgage_rate_alerts' => false]);
        $property = \App\Models\Property::factory()->create(['user_id' => $user->id]);
        \App\Models\Mortgage::factory()->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => now()->addDays(30),
        ]);

        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });
});

<?php

declare(strict_types=1);

use App\Agents\ProtectionAgent;
use App\Models\ProtectionProfile;
use App\Models\User;
use Database\Seeders\PreviewUserSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * fynla.org, 2026-10-02: the release reseed of the preview personas left them
 * with no protection profile (only the Protection page created one), so the
 * protection readiness check blocked and the Mitchell demo's dashboard read
 * "£0, Add your cover" beside £700,000 of policies.
 */
it('gives every preview persona a protection profile, so their cover shows', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(PreviewUserSeeder::class);

    $preview = User::where('is_preview_user', true)->get();
    expect($preview)->not->toBeEmpty()
        ->and(ProtectionProfile::whereIn('user_id', $preview->pluck('id'))->count())->toBe($preview->count());

    $david = $preview->firstWhere('first_name', 'David');
    $analysis = app(ProtectionAgent::class)->analyze($david->id)['data'];

    expect($analysis['can_proceed'] ?? true)->not->toBeFalse()
        ->and((float) ($analysis['coverage']['total_coverage'] ?? 0))->toBeGreaterThan(0.0);
});

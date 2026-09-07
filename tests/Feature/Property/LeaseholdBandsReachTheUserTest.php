<?php

declare(strict_types=1);

use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\PropertyCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * W-0533. `property_ownership.leasehold_reform` is configured with two bands and
 * had no consumer — but it was not orphaned config waiting for a feature. The
 * numbers were COPIED into code in three places:
 *
 *   - `PropertyCalculationService:23`   — the literal `80`
 *   - `PropertyForm.vue:264-265`        — "less than 80 years", "less than 60 years"
 *   - the service docblock              — both bands again, in prose
 *
 * and the one calculation that read any of them was rendered on no surface, so a
 * user with a 62-year lease was told nothing anywhere.
 */
$leasehold = fn (User $user, ?int $years) => Property::factory()->create([
    'user_id' => $user->id,
    'tenure_type' => 'leasehold',
    'lease_remaining_years' => $years,
]);

it('takes both thresholds from configuration rather than a literal', function () {
    $service = (string) file_get_contents(app_path('Services/Property/PropertyCalculationService.php'));

    expect($service)->not->toContain('< 80')
        ->and($service)->toContain('getLeaseholdValuationWarnings');
});

it('warns on the difficult-to-mortgage band', function () use ($leasehold) {
    $property = $leasehold(User::factory()->create(), 70);

    $warnings = app(PropertyCalculationService::class)->leaseholdWarnings($property);

    expect($warnings['has_warnings'])->toBeTrue()
        ->and($warnings['warnings'])->toHaveCount(1)
        ->and($warnings['warnings'][0]['level'])->toBe('warning');
});

it('warns on both bands once the lease is short enough', function () use ($leasehold) {
    $property = $leasehold(User::factory()->create(), 55);

    $warnings = app(PropertyCalculationService::class)->leaseholdWarnings($property);

    expect($warnings['warnings'])->toHaveCount(2)
        ->and(array_column($warnings['warnings'], 'level'))->toBe(['warning', 'danger']);
});

it('says nothing about a long lease', function () use ($leasehold) {
    $property = $leasehold(User::factory()->create(), 950);

    expect(app(PropertyCalculationService::class)->leaseholdWarnings($property)['has_warnings'])->toBeFalse();
});

it('does not confuse an unrecorded term with a safe one', function () use ($leasehold) {
    // Null is "we never asked", and the honest answer is no warning AND no claim.
    $property = $leasehold(User::factory()->create(), null);

    expect(app(PropertyCalculationService::class)->leaseholdWarnings($property))->toBeNull();
});

it('publishes the bands on the property, so both surfaces read one source', function () use ($leasehold) {
    $property = $leasehold(User::factory()->create(), 55);

    $payload = (new PropertyResource($property))->toArray(request());

    expect($payload)->toHaveKey('leasehold_warnings')
        ->and($payload['leasehold_warnings']['warnings'])->toHaveCount(2)
        ->and($payload['leasehold_warnings']['remaining_years'])->toBe(55);
});

it('renders them on web and on /m', function () {
    // Rule 19. Read as files because the defect was that no surface rendered this
    // at all — a component test on either one alone would have missed the other.
    $web = (string) file_get_contents(base_path('resources/js/components/NetWorth/Property/PropertyDetailInline.vue'));
    $mobile = (string) file_get_contents(base_path('resources/mobile/views/modules/PropertyDetail.vue'));

    expect($web)->toContain('leasehold_warnings')
        ->and($mobile)->toContain('leasehold_warnings');
});

it('leaves no hardcoded band in the property form', function () {
    $form = (string) file_get_contents(base_path('resources/js/components/NetWorth/Property/PropertyForm.vue'));

    expect($form)->not->toContain('less than 80 years')
        ->and($form)->not->toContain('less than 60 years')
        ->and($form)->toContain('leaseholdValuationThresholds');
});

<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Protection\ProtectionDataReadinessService;

/*
 * Item 8a (CSJ 2026-10-06): smoking and health are "nice to have" protection
 * checks. With no column default any more, not answered is detectable, and the
 * link goes where the answer is saved (Settings > Health), not /protection.
 */
it('lists smoking and health as missing until the user answers them, linking to the Health settings', function (): void {
    $user = User::factory()->create(['smoking_status' => null, 'health_status' => null]);

    $info = collect(app(ProtectionDataReadinessService::class)->assess($user)['info'])->keyBy('key');

    expect($info['smoker_status']['form_link'])->toBe('/settings/health')
        ->and($info['health_conditions']['form_link'])->toBe('/settings/health');

    $user->update(['smoking_status' => 'never', 'health_status' => 'yes']);
    $info = collect(app(ProtectionDataReadinessService::class)->assess($user->fresh())['info'])->keyBy('key');

    expect($info->has('smoker_status'))->toBeFalse()
        ->and($info->has('health_conditions'))->toBeFalse();
});

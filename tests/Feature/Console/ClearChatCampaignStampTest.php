<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * T23 (plan section 6b, step 2): the chat stamped funnel_answers.campaign =
 * 'savetax' on every registrant (OnboardingChatDirector, removed in T23). A
 * row it stamped has no /savetax income answer, and nor does the linked
 * partner's; a /savetax arrival and the partner of one keep theirs.
 */
uses(RefreshDatabase::class);

it('removes the chat-written stamp and keeps Save Tax arrivals and their partners, dry run first', function (): void {
    $stamped = User::factory()->create(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'assets' => []]]);
    $arrival = User::factory()->create(['funnel_answers' => ['campaign' => 'savetax', 'income' => '50271_100000']]);
    $partner = User::factory()->create(['spouse_id' => $arrival->id, 'funnel_answers' => ['campaign' => 'savetax', 'spouse' => 'yes']]);
    $arrival->forceFill(['spouse_id' => $partner->id])->save();
    $pensioncheck = User::factory()->create(['funnel_answers' => ['campaign' => 'pensioncheck', 'employment' => 'full-time']]);

    $this->artisan('registrations:clear-chat-campaign-stamp', ['--dry-run' => true])
        ->expectsOutputToContain("Would remove the chat's Save Tax stamp from 1 user(s): {$stamped->id}")
        ->assertSuccessful();
    expect($stamped->fresh()->funnel_answers['campaign'] ?? null)->toBe('savetax');

    $this->artisan('registrations:clear-chat-campaign-stamp')
        ->expectsOutputToContain("Removed the chat's Save Tax stamp from 1 user(s): {$stamped->id}")
        ->assertSuccessful();

    expect($stamped->fresh()->funnel_answers)->toEqual(['employment' => 'full-time', 'assets' => []])
        ->and($arrival->fresh()->funnel_answers['campaign'])->toBe('savetax')
        ->and($partner->fresh()->funnel_answers['campaign'])->toBe('savetax')
        ->and($pensioncheck->fresh()->funnel_answers['campaign'])->toBe('pensioncheck');
});

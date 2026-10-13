<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Income\EmploymentIncomeService;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\RecordEditForms;

/*
 * T21 (walk 2026-10-11, M4): a self-employed user's work, opened to change
 * from chat or a page, asks for their profit, as the setup step does
 * (CaptureForms::workFor); an employee's still asks for gross income.
 */
it('opens a self-employed user\'s saved work on the profit form, and an employee\'s on gross income', function (string $status, string $label): void {
    $user = User::factory()->create(['employment_status' => $status]);
    app(EmploymentIncomeService::class)->recordJob($user, 'Okafor Consulting', 'IT consultant', 55000.0);
    $job = $user->employments()->firstOrFail();

    $form = app(RecordEditForms::class)->formFor($user->fresh(), 'employment', (int) $job->id);

    expect($form['name'])->toBe(CaptureForms::WORK)
        ->and($form['schema']['fields']['annual_income']['label'])->toBe($label);
})->with([
    'self-employed' => ['self_employed', 'Annual profit'],
    'employed' => ['employed', 'Gross annual income'],
]);

<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynCaptureTurnInstructions;

it('renders focus label and tool list into the verbatim block', function (): void {
    $out = FynCaptureTurnInstructions::render('Cash & Savings', 'create_savings_account, update_profile, update_record');

    expect($out)->toContain('<asset_capture_turn>')
        ->and($out)->toContain('</asset_capture_turn>')
        ->and($out)->toContain('Cash & Savings')
        ->and($out)->toContain('create_savings_account, update_profile, update_record')
        ->and($out)->toContain('MULTI-ENTITY RULE (highest priority')
        ->and($out)->toContain('EXACTLY ONE')      // the ≤15-word guardrail
        ->and($out)->not->toContain('%1$s')
        ->and($out)->not->toContain('%2$s');
});

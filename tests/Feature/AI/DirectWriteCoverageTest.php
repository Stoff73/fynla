<?php

declare(strict_types=1);

/**
 * S0.5 + S0.7 rollup — coverage assertion.
 *
 * Pins that every `create_*` handler AND the `update_record` handler now
 * persist directly via DB::transaction; no `'action' => 'fill_form'`
 * survives anywhere in the agent. Architectural floor that stops anyone
 * re-introducing a fill_form return on any handler.
 */
it('zero fill_form return sites remain in CoordinatingAgent', function (): void {
    $source = file_get_contents(base_path('app/Agents/CoordinatingAgent.php'));
    preg_match_all("/'action' => 'fill_form'/", $source, $matches);

    expect(count($matches[0]))->toBe(0);
});

it('all create handlers return success: true on the success path', function (): void {
    $source = file_get_contents(base_path('app/Agents/CoordinatingAgent.php'));
    $createHandlers = [
        'handleCreateSavingsAccount',
        'handleCreateInvestmentAccount',
        'handleCreateHolding',
        'handleCreatePension',
        'handleCreateProperty',
        'handleCreateMortgage',
        'handleCreateProtectionPolicy',
        'handleCreateEstateAsset',
        'handleCreateEstateLiability',
        'handleCreateEstateGift',
        'handleCreateFamilyMember',
        'handleCreateTrust',
        'handleCreateBusinessInterest',
        'handleCreateChattel',
        'handleCreateGoal',
        'handleCreateLifeEvent',
    ];

    foreach ($createHandlers as $name) {
        preg_match('/private function '.preg_quote($name).'\(.*?\n    \}/s', $source, $body);
        $handlerBody = $body[0] ?? '';
        expect($handlerBody)->toContain("'success' => true");
        expect($handlerBody)->toContain('DB::transaction');
        expect($handlerBody)->toContain("'created' => true");
    }
});

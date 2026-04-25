<?php

declare(strict_types=1);

/**
 * S0.5.q rollup — coverage assertion.
 *
 * Pins that every `create_*` handler now persists directly via
 * DB::transaction; no `'action' => 'fill_form'` survives in any create
 * path. The one remaining fill_form site lives in handleUpdateRecord
 * (legacy edit path) which is out of scope for S0.5 — Sprint 0.7
 * tackles update_record. This test is the architectural floor that
 * stops anyone re-introducing a fill_form return on a create handler.
 */
it('only one fill_form return site remains and it is on the legacy update path', function (): void {
    $source = file_get_contents(base_path('app/Agents/CoordinatingAgent.php'));
    preg_match_all("/'action' => 'fill_form'/", $source, $matches);

    expect(count($matches[0]))->toBe(1);

    // The lone surviving fill_form must be inside handleUpdateRecord
    // (the edit path 0.7 will rewrite). Other handlers must not regress.
    preg_match('/private function handleUpdateRecord.*?\n    \}/s', $source, $body);
    expect($body[0] ?? '')->toContain("'action' => 'fill_form'");
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

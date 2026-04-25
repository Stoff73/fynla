<?php

declare(strict_types=1);

use App\Constants\UpdateRecordAllowlist;

// ─── Forbidden-field guarantees per spec INV-2.7.3 ─────────────────────

it('rejects Trust.settlor — never AI-mutable (changes ownership semantics)', function (): void {
    expect(UpdateRecordAllowlist::allowedFields('trust'))->not->toContain('settlor');
});

it('rejects Mortgage.start_date — rewriting breaks loan boundaries', function (): void {
    expect(UpdateRecordAllowlist::allowedFields('mortgage'))->not->toContain('start_date');
});

it('rejects Mortgage.mortgage_type — changes the contract type', function (): void {
    expect(UpdateRecordAllowlist::allowedFields('mortgage'))->not->toContain('mortgage_type');
});

it('rejects FamilyMember.relationship — changes household graph', function (): void {
    expect(UpdateRecordAllowlist::allowedFields('family_member'))->not->toContain('relationship');
});

it('rejects identity FKs across every entity', function (): void {
    foreach (UpdateRecordAllowlist::MAP as $entityType => $fields) {
        foreach (['user_id', 'id', 'joint_owner_id', 'household_id', 'trust_id', 'linked_user_id', 'holdable_id'] as $forbidden) {
            expect($fields)->not->toContain($forbidden, "{$entityType} allowlist must not contain {$forbidden}");
        }
    }
});

it('rejects audit timestamps across every entity', function (): void {
    foreach (UpdateRecordAllowlist::MAP as $entityType => $fields) {
        foreach (['created_at', 'updated_at', 'deleted_at'] as $forbidden) {
            expect($fields)->not->toContain($forbidden, "{$entityType} allowlist must not contain {$forbidden}");
        }
    }
});

// ─── Coverage guarantees ────────────────────────────────────────────────

it('covers every entity_type the update_record handler can resolve', function (): void {
    $expected = [
        'goal', 'life_event', 'savings_account', 'investment_account',
        'dc_pension', 'db_pension', 'property', 'mortgage',
        'life_insurance', 'critical_illness', 'income_protection',
        'estate_asset', 'estate_liability', 'estate_gift',
        'family_member', 'trust', 'business_interest', 'chattel',
    ];

    expect(UpdateRecordAllowlist::entityTypes())->toEqualCanonicalizing($expected);
});

it('returns an empty list for unknown entity types', function (): void {
    expect(UpdateRecordAllowlist::allowedFields('unicorn'))->toBe([]);
    expect(UpdateRecordAllowlist::allowedFields(''))->toBe([]);
});

it('aggregates a union of all allowed field names', function (): void {
    $union = UpdateRecordAllowlist::allFieldNames();

    // Sanity: union contains a representative field from every entity type.
    expect($union)->toContain('goal_name');
    expect($union)->toContain('current_balance');
    expect($union)->toContain('outstanding_balance');
    expect($union)->toContain('sum_assured');
    expect($union)->toContain('chattel_type');

    // Union must not contain forbidden fields under any entity.
    expect($union)->not->toContain('user_id');
    expect($union)->not->toContain('settlor');
    expect($union)->not->toContain('relationship');
    expect($union)->not->toContain('start_date');
    expect($union)->not->toContain('mortgage_type');
});

it('every allowlist entry is a non-empty list of strings', function (): void {
    foreach (UpdateRecordAllowlist::MAP as $entityType => $fields) {
        expect($fields)->toBeArray()->not->toBeEmpty("{$entityType} allowlist is empty");
        foreach ($fields as $field) {
            expect($field)->toBeString();
        }
    }
});

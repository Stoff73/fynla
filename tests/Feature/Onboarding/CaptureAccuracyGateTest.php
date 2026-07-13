<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\CaptureAccuracyGate;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks a bare ISA even when the model invents a cash subtype', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'My ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'I have an ISA with £20,000');

    expect($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toContain('ISA type')
        ->and($result['missing'])->toContain('isa_subtype');
});

it('allows an explicitly individual Cash ISA', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'My Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'My Cash ISA is mine alone and has £20,000');

    expect($result)->toBe(['allowed' => true]);
});

it('blocks an ISA tool whose subtype contradicts the users words', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'My Stocks & Shares ISA is mine alone and has £20,000');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('isa_subtype');
});

it('blocks a Cash ISA whose persisted ISA flag is false', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => false,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'My Cash ISA is individually owned.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('isa_subtype');
});

it('blocks an investment subtype that conflicts with its persisted account type', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_investment_account', [
        'account_name' => 'Vanguard ISA',
        'account_type' => 'gia',
        'isa_type' => 'stocks_and_shares',
        'current_value' => 20000,
        'ownership_type' => 'individual',
    ], 'My Vanguard Stocks & Shares ISA is individually owned.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('isa_subtype');
});

it('accepts a canonical ISA wrapper with a coherent subtype', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_investment_account', [
        'account_name' => 'Vanguard ISA',
        'account_type' => 'isa',
        'isa_type' => 'stocks_and_shares',
        'current_value' => 20000,
        'ownership_type' => 'individual',
    ], 'My Vanguard Stocks & Shares ISA is individually owned.');

    expect($result)->toBe(['allowed' => true]);
});

it('keeps Junior ISA distinct from Cash ISA', function (): void {
    $mismatch = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Child account',
        'account_type' => 'junior_isa',
        'is_isa' => true,
        'current_balance' => 5000,
        'ownership_type' => 'individual',
    ], 'It is a Cash ISA and is individually owned.');
    $correct = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Child account',
        'account_type' => 'junior_isa',
        'is_isa' => true,
        'current_balance' => 5000,
        'ownership_type' => 'individual',
    ], 'It is a Junior ISA and is individually owned.');

    expect($mismatch['allowed'])->toBeFalse()
        ->and($mismatch['missing'])->toContain('isa_subtype')
        ->and($correct)->toBe(['allowed' => true]);
});

it('does not accept an elliptical ISA subtype without prior ISA evidence', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'Cash. Individually.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('isa_subtype');
});

it('does not treat a negated ISA subtype as positive evidence', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'This account is not a Cash ISA. It is individually owned.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('isa_subtype');
});

it('blocks household wording that the model changes to individual ownership', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'Our savings are £20,000');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('does not treat a possessive as explicit ownership confirmation', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'My savings account has £20,000');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('requires the joint owner and explicit share before a joint write', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Joint Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
    ], 'Our joint savings are £20,000');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('joint_owner_id')
        ->and($result['missing'])->toContain('ownership_percentage');
});

it('allows a fully evidenced joint ownership write', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Joint Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 42,
        'ownership_percentage' => 50,
    ], 'Our joint savings are £20,000, owned 50/50 with my spouse');

    expect($result)->toBe(['allowed' => true]);
});

it('scopes subtype and ownership evidence to each account in a mixed turn', function (): void {
    $text = 'My Cash ISA is individually owned. My Nationwide saver is jointly owned 50/50 with my spouse.';
    $cashIsa = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'My Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], $text);
    $jointSaver = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Nationwide saver',
        'account_type' => 'easy_access',
        'provider' => 'Nationwide',
        'current_balance' => 10000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], $text);

    expect($cashIsa)->toBe(['allowed' => true])
        ->and($jointSaver)->toBe(['allowed' => true]);
});

it('fails closed when a shared provider identifies more than one account', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Saver',
        'provider' => 'Barclays',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], 'My Barclays Saver is individually owned. My Barclays current account is jointly owned 50/50 with my spouse.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('scopes ownership evidence within a one-sentence multi-account turn', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Saver',
        'provider' => 'Barclays',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], 'My Barclays Saver is individually owned and my Nationwide account is jointly owned 50/50 with my spouse.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('scopes ownership evidence across ordinary clause separators', function (string $text): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Saver',
        'provider' => 'Barclays',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], $text);

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
})->with([
    'semicolon' => 'My Barclays Saver is individually owned; my Nationwide account is jointly owned 50/50 with my spouse.',
    'comma' => 'My Barclays Saver is individually owned, while my Nationwide account is jointly owned 50/50 with my spouse.',
    'whereas' => 'My Barclays Saver is individually owned whereas my Nationwide account is jointly owned 50/50 with my spouse.',
    'omitted repeated pronoun' => 'My Barclays Saver is individually owned and Nationwide account is jointly owned 50/50 with my spouse.',
]);

it('fails closed on conflicting uncorrected shares in one selected segment', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Saver',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], 'My Barclays Saver is jointly owned and I own 60%, and Nationwide is jointly owned and I own 50%.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_percentage');
});

it('does not borrow a correction that names a different account', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Saver',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'My Barclays Saver is jointly owned 50/50, actually my Nationwide Saver is individually owned.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('does not borrow a punctuated correction that names a different account', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Saver',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'My Barclays Saver is jointly owned 50/50, actually, my Nationwide Saver is individually owned.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('does not borrow a punctuated correction with an omitted pronoun', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Saver',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'My Barclays Saver is jointly owned 50/50, actually, Nationwide Saver is individually owned.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('does not borrow a corrected conjunction with an omitted pronoun', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Saver',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'My Barclays Saver is jointly owned 50/50 but actually Nationwide Saver is individually owned.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('fails closed when a property type identifies more than one property', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_property', [
        'property_type' => 'buy_to_let',
        'current_value' => 300000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], 'The Leeds buy to let is individually owned. The York buy to let is jointly owned 50/50 with my spouse.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type');
});

it('blocks joint ownership for an ISA before the write handler', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], 'My Cash ISA is jointly owned 50/50 with my spouse.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type')
        ->and($result['reason'])->toContain('ISA must be recorded as individually owned');
});

it('rejects a partial percentage on an individually owned record', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Personal saver',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 50,
    ], 'My Personal saver is individually owned.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_percentage');
});

it('blocks a tool share that contradicts the users stated ownership share', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Joint savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], 'We own it jointly, owned 60% by me and 40% by my spouse.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_percentage');
});

it('rejects negated equal-share wording', function (string $text): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Joint savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 50,
    ], $text);

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_percentage');
})->with([
    'half is rejected' => 'Our savings are joint. My share isn\'t half.',
    'equal ownership is rejected' => 'Our savings are joint. Ownership is not equal.',
]);

it('uses the latest corrected ownership share', function (): void {
    $gate = app(CaptureAccuracyGate::class);
    $correctedToSixty = $gate->inspect('create_savings_account', [
        'account_name' => 'Joint savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 60,
    ], 'Our savings are jointly owned in equal shares. Actually, my share is 60%.');
    $correctedToForty = $gate->inspect('create_savings_account', [
        'account_name' => 'Joint savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'ownership_percentage' => 40,
    ], 'Our savings are jointly owned and I own 60%. Correction, my share is 40%.');

    expect($correctedToSixty)->toBe(['allowed' => true])
        ->and($correctedToForty)->toBe(['allowed' => true]);
});

it('uses the latest explicit ownership form without collapsing tenants in common', function (): void {
    $trustMismatch = app(CaptureAccuracyGate::class)->inspect('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 400000,
        'ownership_type' => 'individual',
    ], 'The property is owned by the trust');

    $tenantsMismatch = app(CaptureAccuracyGate::class)->inspect('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 400000,
        'ownership_type' => 'joint',
        'joint_owner_id' => 42,
        'ownership_percentage' => 60,
    ], 'We own it as tenants in common, with my share at 60%');

    expect($trustMismatch['allowed'])->toBeFalse()
        ->and($trustMismatch['missing'])->toContain('ownership_type')
        ->and($tenantsMismatch['allowed'])->toBeFalse()
        ->and($tenantsMismatch['missing'])->toContain('ownership_type');
});

it('blocks an investment ownership form that the investment store cannot preserve', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_investment_account', [
        'account_type' => 'personal_investment_account',
        'current_value' => 50000,
        'ownership_type' => 'trust',
    ], 'It is owned by the trust.');

    expect($result['allowed'])->toBeFalse()
        ->and($result['missing'])->toContain('ownership_type')
        ->and($result['reason'])->toContain('individually or jointly');
});

it('accepts accumulated elliptical clarification evidence', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'I have an ISA with £20,000. Cash. Individually.');

    expect($result)->toBe(['allowed' => true]);
});

it('accepts subtype and ownership supplied in the same concise clarification', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'I have an ISA with £20,000. Cash, mine alone.');

    expect($result)->toBe(['allowed' => true]);
});

it('accepts subtype and ownership joined by a natural conjunction', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], 'I have an ISA with £20,000. Cash and individually.');

    expect($result)->toBe(['allowed' => true]);
});

it('requires trust_id in every strict schema that offers trust ownership', function (string $relativePath): void {
    $document = file_get_contents(base_path($relativePath));
    preg_match('/```json\s*(\{.*\})\s*```/s', $document, $matches);
    $schema = json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
    $parameters = $schema['parameters'];

    expect($parameters['properties'])->toHaveKey('trust_id')
        ->and($parameters['required'])->toContain('trust_id');
})->with([
    'savings' => 'fyn-memory/procedural/tool_schema/savings/create_savings_account.xai.md',
    'property' => 'fyn-memory/procedural/tool_schema/property/create_property.xai.md',
    'liability' => 'fyn-memory/procedural/tool_schema/estate/create_liability.xai.md',
]);

it('performs zero writes when a tool call is more precise than the user', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'I have an ISA with £20,000',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], $user, $conversation->id);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
});

it('persists one joint savings record with the confirmed owner and share', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Our joint savings are £20,000, owned 60% by me and 40% by my spouse',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Joint Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 60,
    ], $user, $conversation->id);

    expect($result['success'] ?? false)->toBeTrue()
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(1);

    $account = SavingsAccount::where('user_id', $user->id)->sole();
    expect($account->joint_owner_id)->toBe($spouse->id)
        ->and((float) $account->ownership_percentage)->toBe(60.0)
        ->and($account->ownership_type)->toBe('joint');
});

it('rejects an unrelated user as a joint owner', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $unrelated = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Our joint savings are £20,000, owned 60% by me and 40% by my spouse',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Joint Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $unrelated->id,
        'ownership_percentage' => 60,
    ], $user, $conversation->id);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['missing'] ?? [])->toContain('joint_owner_id')
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
});

it('performs zero writes when the tool changes the confirmed ownership share', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Our joint savings are owned 60% by me and 40% by my spouse',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Joint Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 50,
    ], $user, $conversation->id);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['missing'] ?? [])->toContain('ownership_percentage')
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
});

it('does not attach a spouse from negated joint ownership evidence', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My Personal saver is individually owned. It is not jointly owned.',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Personal saver',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 100,
    ], $user, $conversation->id);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
});

it('rejects an unrelated joint owner even without conversation evidence', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $unrelated = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Joint Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $unrelated->id,
        'ownership_percentage' => 50,
    ], $user, null);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['missing'] ?? [])->toContain('joint_owner_id')
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
});

it('rejects a joint owner id on an individual record without conversation evidence', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $unrelated = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Personal Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
        'joint_owner_id' => $unrelated->id,
    ], $user, null);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['missing'] ?? [])->toContain('ownership_type')
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
});

it('normalises a strict-schema zero owner sentinel on an individual record', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My Barclays Cash ISA has £20,000 and is owned individually.',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Barclays Cash ISA',
        'account_type' => 'cash_isa',
        'current_balance' => 20000,
        'is_isa' => true,
        'ownership_type' => 'individual',
        'joint_owner_id' => 0,
        'trust_id' => 0,
        'ownership_percentage' => 100,
    ], $user, $conversation->id);

    expect($result['success'] ?? false)->toBeTrue();
    $account = SavingsAccount::where('user_id', $user->id)->sole();
    expect($account->joint_owner_id)->toBeNull()
        ->and($account->trust_id)->toBeNull()
        ->and($account->ownership_type)->toBe('individual');
});

it('keeps anaphoric ownership evidence with the named ISA from the preceding sentence', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_savings_account', [
        'account_name' => 'Barclays Cash ISA',
        'provider' => 'Barclays',
        'account_type' => 'cash_isa',
        'current_balance' => 20000,
        'is_isa' => true,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], 'My Barclays account is a Cash ISA with £20,000. I added £5,000 this tax year and own it individually.');

    expect($result['allowed'])->toBeTrue();
});

it('rejects a property link to another users trust', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $otherUser = User::factory()->create(['is_preview_user' => false]);
    $ownTrust = Trust::factory()->create(['user_id' => $user->id]);
    $foreignTrust = Trust::factory()->create(['user_id' => $otherUser->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My main residence is owned by the trust.',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 450000,
        'ownership_type' => 'trust',
        'ownership_percentage' => 100,
        'trust_id' => $foreignTrust->id,
    ], $user, $conversation->id);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['missing'] ?? [])->toContain('trust_id')
        ->and(Property::where('user_id', $user->id)->count())->toBe(0);
});

it('persists only a household-scoped trust on a trust-owned liability', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $otherUser = User::factory()->create(['is_preview_user' => false]);
    $ownTrust = Trust::factory()->create(['user_id' => $user->id]);
    $foreignTrust = Trust::factory()->create(['user_id' => $otherUser->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'The trust loan is owned by the trust.',
    ]);
    $arguments = [
        'liability_name' => 'Trust loan',
        'liability_type' => 'personal_loan',
        'current_balance' => 20000,
        'ownership_type' => 'trust',
        'ownership_percentage' => 100,
    ];

    $rejected = app(CoordinatingAgent::class)->executeTool(
        'create_liability',
        [...$arguments, 'trust_id' => $foreignTrust->id],
        $user,
        $conversation->id,
    );
    expect($rejected['clarification_required'] ?? false)->toBeTrue()
        ->and(Liability::where('user_id', $user->id)->count())->toBe(0);

    $created = app(CoordinatingAgent::class)->executeTool(
        'create_liability',
        [...$arguments, 'trust_id' => $ownTrust->id],
        $user,
        $conversation->id,
    );
    expect($created['success'] ?? false)->toBeTrue()
        ->and(Liability::where('user_id', $user->id)->sole()->trust_id)->toBe($ownTrust->id);
});

it('persists only a household-scoped trust on trust-owned savings', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $otherUser = User::factory()->create(['is_preview_user' => false]);
    $ownTrust = Trust::factory()->create(['user_id' => $user->id]);
    $foreignTrust = Trust::factory()->create(['user_id' => $otherUser->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'The family reserve account is owned by the trust.',
    ]);
    $arguments = [
        'account_name' => 'Family reserve',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'trust',
        'ownership_percentage' => 100,
    ];

    $rejected = app(CoordinatingAgent::class)->executeTool(
        'create_savings_account',
        [...$arguments, 'trust_id' => $foreignTrust->id],
        $user,
        $conversation->id,
    );
    expect($rejected['clarification_required'] ?? false)->toBeTrue()
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);

    $created = app(CoordinatingAgent::class)->executeTool(
        'create_savings_account',
        [...$arguments, 'trust_id' => $ownTrust->id],
        $user,
        $conversation->id,
    );
    expect($created['success'] ?? false)->toBeTrue()
        ->and(SavingsAccount::where('user_id', $user->id)->sole()->trust_id)->toBe($ownTrust->id);
});

it('rejects foreign ownership links through the direct savings API', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $otherUser = User::factory()->create(['is_preview_user' => false]);
    $ownTrust = Trust::factory()->create(['user_id' => $user->id]);
    $foreignTrust = Trust::factory()->create(['user_id' => $otherUser->id]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    $this->actingAs($user, 'sanctum')->postJson('/api/savings/accounts', [
        'account_name' => 'Injected trust account',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'trust',
        'ownership_percentage' => 100,
        'trust_id' => $foreignTrust->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['trust_id']);

    $this->actingAs($user, 'sanctum')->postJson('/api/savings/accounts', [
        'account_name' => 'Injected joint account',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $otherUser->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['joint_owner_id']);

    foreach (['joint', 'tenants_in_common'] as $ownershipType) {
        $this->actingAs($user, 'sanctum')->postJson('/api/savings/accounts', [
            'account_name' => 'Missing explicit share',
            'account_type' => 'easy_access',
            'current_balance' => 20000,
            'ownership_type' => $ownershipType,
            'joint_owner_id' => $spouse->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ownership_percentage']);
    }

    foreach ([0, 100] as $invalidShare) {
        $this->actingAs($user, 'sanctum')->postJson('/api/savings/accounts', [
            'account_name' => 'Invalid explicit share',
            'account_type' => 'easy_access',
            'current_balance' => 20000,
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => $invalidShare,
            'joint_owner_id' => $spouse->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ownership_percentage']);
    }

    $this->actingAs($user, 'sanctum')->postJson('/api/savings/accounts', [
        'account_name' => 'Household trust account',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'trust',
        'ownership_percentage' => 100,
        'trust_id' => $ownTrust->id,
    ])->assertCreated();

    expect(SavingsAccount::where('user_id', $user->id)->sole()->trust_id)->toBe($ownTrust->id);
});

it('prevents direct savings updates from creating a shared ISA', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    $individualIsa = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'joint_owner_id' => null,
    ]);
    $this->actingAs($user, 'sanctum')->putJson("/api/savings/accounts/{$individualIsa->id}", [
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $spouse->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['ownership_type']);

    $jointSavings = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'easy_access',
        'is_isa' => false,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $spouse->id,
    ]);
    $this->actingAs($user, 'sanctum')->putJson("/api/savings/accounts/{$jointSavings->id}", [
        'account_type' => 'cash_isa',
        'is_isa' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['ownership_type']);

    expect($individualIsa->fresh()->ownership_type)->toBe('individual')
        ->and($jointSavings->fresh()->is_isa)->toBeFalse();
});

it('persists a tenants-in-common liability share', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'The family loan is owned as tenants in common, with my share at 60%.',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_liability', [
        'liability_name' => 'Family loan',
        'liability_type' => 'personal_loan',
        'current_balance' => 20000,
        'ownership_type' => 'tenants_in_common',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 60,
    ], $user, $conversation->id);

    expect($result['success'] ?? false)->toBeTrue();
    $liability = Liability::where('user_id', $user->id)->sole();
    expect($liability->ownership_type)->toBe('tenants_in_common')
        ->and($liability->joint_owner_id)->toBe($spouse->id)
        ->and($liability->ownership_percentage)->toBe(60.0);
});

it('fails closed when an ownership write has no conversation evidence', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Personal Savings',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], $user, null);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['missing'] ?? [])->toContain('ownership_type')
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
});

it('carries missing accuracy facts across successive clarification turns', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $arguments = [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ];

    foreach (['I have an ISA with £20,000', 'Cash', 'Individually'] as $index => $message) {
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);
        $result = app(CoordinatingAgent::class)->executeTool(
            'create_savings_account',
            $arguments,
            $user,
            $conversation->id,
        );

        if ($index < 2) {
            expect($result['clarification_required'] ?? false)->toBeTrue();
        }
    }

    expect($result['success'] ?? false)->toBeTrue()
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(1);
});

it('uses the bounded conversation evidence when the tool is called only after all clarifications', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    foreach (['I have a Barclays ISA with £20,000', 'Cash', 'Individually'] as $message) {
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);
    }

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Barclays ISA',
        'provider' => 'Barclays',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
    ], $user, $conversation->id);

    expect($result['success'] ?? false)->toBeTrue()
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(1);
});

it('does not reuse ownership evidence for a different account using the same tool', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My First Saver is jointly owned 60% by me and 40% by my spouse.',
    ]);
    app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'First Saver',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 50,
    ], $user, $conversation->id);

    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My Second Saver is jointly owned with my spouse.',
    ]);
    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Second Saver',
        'account_type' => 'easy_access',
        'current_balance' => 12000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 60,
    ], $user, $conversation->id);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['missing'] ?? [])->toContain('ownership_percentage')
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(0);
});

it('propagates authorised joint ownership to an auto-created mortgage', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My main residence is jointly owned 50/50 with my spouse and has a £200,000 mortgage.',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_property', [
        'property_type' => 'main_residence',
        'current_value' => 450000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 50,
        'has_mortgage' => true,
        'mortgage_outstanding_balance' => 200000,
    ], $user, $conversation->id);

    $property = Property::findOrFail($result['entity_id']);
    $mortgage = Mortgage::where('property_id', $property->id)->sole();
    expect($result['success'] ?? false)->toBeTrue()
        ->and($property->joint_owner_id)->toBe($spouse->id)
        ->and($mortgage->joint_owner_id)->toBe($spouse->id)
        ->and(Mortgage::query()
            ->where(fn ($query) => $query->where('user_id', $spouse->id)->orWhere('joint_owner_id', $spouse->id))
            ->count())->toBe(1);
});

it('inherits a property joint owner when adding its mortgage later', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $spouse->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'address_line_1' => '12 Joint Street',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_mortgage', [
        'property_address_hint' => '12 Joint Street',
        'outstanding_balance' => 150000,
    ], $user, null);

    $mortgage = Mortgage::where('property_id', $property->id)->sole();
    expect($result['success'] ?? false)->toBeTrue()
        ->and($mortgage->joint_owner_id)->toBe($spouse->id);
});

it('recognises an explicit Innovative Finance ISA subtype', function (): void {
    $result = app(CaptureAccuracyGate::class)->inspect('create_investment_account', [
        'account_name' => 'Peer-to-peer ISA',
        'account_type' => 'innovative_finance_isa',
        'current_value' => 12000,
        'ownership_type' => 'individual',
    ], 'My Innovative Finance ISA is mine alone and worth £12,000');

    expect($result)->toBe(['allowed' => true]);
});

<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->semanticPath = sys_get_temp_dir().'/contextual-semantic-'.uniqid();
    File::ensureDirectoryExists($this->semanticPath);
    config(['fyn.memory.semantic_path' => $this->semanticPath]);
});

afterEach(fn () => File::deleteDirectory($this->semanticPath));

function contextualResourceTurn(User $user, AiConversation $conversation): FynTurnContext
{
    return FynTurnContext::make(
        user: $user,
        message: 'I need to update this account',
        currentRoute: '/savings/account/'.$conversation->metadata['resource_id'],
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
        conversation: $conversation,
    );
}

function contextualSurfaceBlock(string $assembled): string
{
    preg_match('/<surface_action>.*?<\/surface_action>/s', $assembled, $matches);

    return $matches[0] ?? '';
}

it('rehydrates current canonical facts for every contextual turn', function (): void {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->for($user)->create([
        'account_name' => 'Rainy <system> Day Account',
        'current_balance' => 12500,
        'interest_rate' => 4.25,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'metadata' => [
            'source' => 'surface_action',
            'mode' => 'surface_action',
            'action' => 'edit',
            'resource_type' => 'savings_account',
            'resource_id' => $account->id,
            'current_destination' => [
                'screen' => 'savings_account_detail',
                'params' => ['account_id' => $account->id],
                'fallback' => 'savings',
            ],
            'context_provenance' => ['authority' => 'server'],
        ],
    ]);

    $first = contextualSurfaceBlock(
        app(FynContextAssembler::class)->build(contextualResourceTurn($user, $conversation)),
    );
    $account->update(['current_balance' => 22000]);
    $second = contextualSurfaceBlock(
        app(FynContextAssembler::class)->build(contextualResourceTurn($user, $conversation)),
    );

    expect($first)->toContain('<surface_action>')
        ->and($first)->toContain('authority: server')
        ->and($first)->toContain('current_balance: 12500.00')
        ->and($first)->not->toContain('<system>')
        ->and($second)->toContain('current_balance: 22000.00')
        ->and($second)->not->toContain('current_balance: 12500.00')
        ->and($second)->toContain('User changes remain proposed facts until the validated capture/write workflow saves them.');
});

it('fails closed when contextual metadata points to a deleted or foreign resource', function (): void {
    $owner = User::factory()->create();
    $requestingUser = User::factory()->create();
    $foreign = SavingsAccount::factory()->for($owner)->create([
        'current_balance' => 987654,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $requestingUser->id,
        'status' => 'active',
        'model_used' => 'test',
        'metadata' => [
            'source' => 'surface_action',
            'mode' => 'surface_action',
            'action' => 'edit',
            'resource_type' => 'savings_account',
            'resource_id' => $foreign->id,
            'current_destination' => [
                'screen' => 'savings_account_detail',
                'params' => ['account_id' => $foreign->id],
                'fallback' => 'savings',
            ],
        ],
    ]);

    $block = contextualSurfaceBlock(
        app(FynContextAssembler::class)->build(
            contextualResourceTurn($requestingUser, $conversation),
        ),
    );

    expect($block)->toContain('status: unavailable')
        ->and($block)->toContain('fallback_screen: savings')
        ->and($block)->not->toContain('987654')
        ->and($block)->not->toContain('current_balance');
});

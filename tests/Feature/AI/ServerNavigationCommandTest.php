<?php

declare(strict_types=1);

use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * T6 (walk fix plan B2.5; F3, F4). Navigation is decided on the server for
 * every surface, before the model: a plain command naming a page opens it
 * with the existing `navigation` event, and the request and reply are saved.
 * F4: "ok take me to my tax strategy" was saved with no reply and showed
 * "I couldn't pick up that request".
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'tier' => 'free']);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    $this->conversation = AiConversation::create(['user_id' => $this->user->id, 'status' => 'active', 'model_used' => 'test', 'title' => 'Chat']);
    Sanctum::actingAs($this->user);
});

function t6Events(string $body): array
{
    return collect(explode("\n\n", $body))
        ->map(fn (string $frame) => trim(preg_replace('/^data:\s*/', '', trim($frame))))
        ->filter(fn (string $json) => $json !== '' && $json !== '[DONE]')
        ->map(fn (string $json) => json_decode($json, true))
        ->filter()
        ->values()
        ->all();
}

it('opens the tax strategy page on web and saves the request and the reply (F4)', function (): void {
    FynStreamHarness::fake()->bind(); // a navigation turn never reaches the model

    $body = $this->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", [
        'message' => 'ok take me to my tax strategy',
        'current_route' => '/dashboard',
    ])->assertOk()->streamedContent();

    $events = collect(t6Events($body));
    $navigation = $events->firstWhere('type', 'navigation');

    expect($events->firstWhere('type', 'content')['text'])->toBe('Opening Tax Strategy.')
        ->and($navigation['route_path'])->toBe('/tax-strategy')
        ->and($navigation['destination']['screen'])->toBe('tax_strategy')
        ->and($events->last()['type'])->toBe('done');

    $rows = $this->conversation->messages()->orderBy('id')->get(['role', 'content']);
    expect($rows->pluck('role')->all())->toBe(['user', 'assistant'])
        ->and($rows[0]->content)->toBe('ok take me to my tax strategy')
        ->and($rows[1]->content)->toBe('Opening Tax Strategy.');
});

it('sends each surface its own page for the same command', function (array $headers, string $route): void {
    FynStreamHarness::fake()->bind(); // a navigation turn never reaches the model

    $body = $this->withHeaders($headers)->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", [
        'message' => 'take me to my savings',
    ])->assertOk()->streamedContent();

    $navigation = collect(t6Events($body))->firstWhere('type', 'navigation');

    expect($navigation['route_path'])->toBe($route)
        ->and($navigation['destination']['screen'])->toBe('savings');
})->with([
    'web' => [[], '/net-worth/cash'],
    '/m' => [['X-Fynla-Surface' => 'm'], '/savings'],
    'native' => [['X-Fynla-Client' => 'ios'], '/savings'],
]);

it('says a website-only page is on the website, and does not navigate, on /m', function (): void {
    FynStreamHarness::fake()->bind(); // a navigation turn never reaches the model

    $body = $this->withHeaders(['X-Fynla-Surface' => 'm'])
        ->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", ['message' => 'open the will builder'])
        ->assertOk()->streamedContent();

    $events = collect(t6Events($body));

    expect($events->firstWhere('type', 'navigation'))->toBeNull()
        ->and($events->firstWhere('type', 'content')['text'])->toBe('Will Builder is only on the website for now.');
});

it('leaves a question that mentions a page to the answer process (F3)', function (): void {
    FynStreamHarness::fake()->textTurn('Here is the working.')->bind();

    $body = $this->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages", [
        'message' => 'My retirement page says £484,572 at 60. Show the working.',
    ])->assertOk()->streamedContent();

    expect(collect(t6Events($body))->firstWhere('type', 'navigation'))->toBeNull()
        ->and($body)->not->toContain('Opening ');
});

it('navigates a queued command the same way', function (): void {
    FynStreamHarness::fake()->bind(); // a navigation turn never reaches the model

    $queued = $this->conversation->messages()->create([
        'role' => 'user',
        'content' => 'go to my goals',
        'status' => AiMessageStatus::Queued->value,
    ]);

    $body = $this->postJson("/api/ai-chat/conversations/{$this->conversation->id}/messages/{$queued->id}/stream")
        ->assertOk()->streamedContent();

    $navigation = collect(t6Events($body))->firstWhere('type', 'navigation');

    expect($navigation['route_path'])->toBe('/goals')
        ->and($this->conversation->messages()->where('role', 'user')->count())->toBe(1)
        ->and($this->conversation->messages()->where('role', 'assistant')->value('content'))->toBe('Opening Goals.');
});

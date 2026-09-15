<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    $this->conversation = AiConversation::create(['user_id' => $this->user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding']);
    Sanctum::actingAs($this->user);
});

function postForm($test, int $conversationId, array $body)
{
    return $test->postJson("/api/ai-chat/conversations/{$conversationId}/messages", $body);
}

it('rejects an unknown form name', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'bank', 'answers' => []]])
        ->assertStatus(422)->assertJsonValidationErrors(['form.name']);
});

it('rejects a kind the schema does not have and a bad ownership value', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'castle' => ['current_value' => 1],
        'main_residence' => ['current_value' => 1, 'mortgage_outstanding_balance' => null, 'ownership_type' => 'shared'],
    ]]])->assertStatus(422)->assertJsonValidationErrors(['form.answers.castle', 'form.answers.main_residence.ownership_type']);
});

it('requires the mortgage key to be present even when null, and a value for a filled kind', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'buy_to_let' => ['ownership_type' => 'individual', 'monthly_rental_income' => 900],
    ]]])->assertStatus(422)->assertJsonValidationErrors(['form.answers.buy_to_let.current_value', 'form.answers.buy_to_let.mortgage_outstanding_balance']);
});

it('still requires a message when there is no form', function (): void {
    postForm($this, $this->conversation->id, [])->assertStatus(422)->assertJsonValidationErrors(['message']);
});

it('accepts a well-formed property answer with no message', function (): void {
    // Past validation the controller streams; a 200 with a streamed body is
    // the proof the request class let it through (the director's own
    // behaviour is Task 5's test).
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
    ]]])->assertOk();
});

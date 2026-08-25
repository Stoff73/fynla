<?php

declare(strict_types=1);

namespace Tests;

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Drop any authentication bound earlier in the test (e.g. a
     * Sanctum::actingAs() in beforeEach) so the next request runs as a guest.
     *
     * This exists to replace the old `$this->app = $this->createApplication();`
     * pattern in "requires authentication" tests. Replacing the application
     * instance silently opened a SECOND database connection outside
     * RefreshDatabase's transaction, so any factory write made after the swap
     * was committed permanently to the test database — a test-isolation leak
     * that seeded random users_email_unique faker collisions in later tests.
     *
     * Sanctum::actingAs() only does guard($g)->setUser($user) + shouldUse($g);
     * forgetting the instantiated guards makes the next request re-resolve the
     * guard from the (absent) bearer token, yielding a genuine 401 while every
     * DB write stays inside the test transaction.
     */
    protected function actingAsGuest(): void
    {
        app('auth')->forgetGuards();
    }

    /**
     * Exercise an ownership-sensitive capture handler with explicit user
     * evidence. CaptureAccuracyGate deliberately fails closed without a chat
     * transcript, while direct-write tests below this boundary still need to
     * reach the handler behaviour they are asserting.
     */
    protected function executeCaptureToolWithEvidence(string $tool, array $input, User $user): array
    {
        $input['ownership_type'] ??= 'individual';
        $name = (string) (
            $input['account_name']
            ?? $input['liability_name']
            ?? $input['address_line_1']
            ?? str_replace('_', ' ', (string) ($input['property_type'] ?? 'record'))
        );
        $isaLabel = match ($input['account_type'] ?? null) {
            'cash_isa' => 'a Cash ISA',
            'junior_isa' => 'a Junior ISA',
            'stocks_shares_isa' => 'a Stocks & Shares ISA',
            'lifetime_isa' => 'a Lifetime ISA',
            'innovative_finance_isa' => 'an Innovative Finance ISA',
            default => null,
        };
        $ownership = match ($input['ownership_type']) {
            'joint' => 'jointly owned with my spouse, with my share at '.(float) ($input['ownership_percentage'] ?? 50).'%',
            'tenants_in_common' => 'owned as tenants in common, with my share at '.(float) ($input['ownership_percentage'] ?? 50).'%',
            'trust' => 'owned by the trust',
            default => 'owned individually',
        };
        $description = $isaLabel === null ? $ownership : "{$isaLabel} and {$ownership}";

        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => "{$name} is {$description}.",
        ]);

        return app(CoordinatingAgent::class)->executeTool(
            $tool,
            $input,
            $user,
            $conversation->id,
        );
    }
}

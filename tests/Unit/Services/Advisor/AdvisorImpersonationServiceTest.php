<?php

declare(strict_types=1);

use App\Models\AdvisorClient;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Advisor\AdvisorImpersonationService;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->service = new AdvisorImpersonationService;
    $this->advisor = User::factory()->create(['is_advisor' => true]);

    // Create a real Sanctum token so currentAccessToken()->id works
    $token = $this->advisor->createToken('test-token');
    $this->advisor->withAccessToken($token->accessToken);
});

it('stores impersonation state in cache on enter', function () {
    $client = User::factory()->create();
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $result = $this->service->enterClientProfile($this->advisor, $client);

    expect($result['impersonating'])->toBeTrue()
        ->and($result['client']['id'])->toBe($client->id)
        ->and($result['client'])->toHaveKeys(['id', 'first_name', 'surname', 'email']);

    $tokenId = $this->advisor->currentAccessToken()->id;
    $cached = Cache::get("advisor_impersonation:{$tokenId}");

    expect($cached)->not->toBeNull()
        ->and($cached['client_id'])->toBe($client->id);
});

it('clears cache on exit', function () {
    $client = User::factory()->create();
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->service->enterClientProfile($this->advisor, $client);

    $tokenId = $this->advisor->currentAccessToken()->id;
    expect(Cache::has("advisor_impersonation:{$tokenId}"))->toBeTrue();

    $this->service->exitClientProfile($this->advisor);

    expect(Cache::has("advisor_impersonation:{$tokenId}"))->toBeFalse();
});

it('detects active impersonation', function () {
    $client = User::factory()->create();
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    expect($this->service->isImpersonating($this->advisor))->toBeFalse();

    $this->service->enterClientProfile($this->advisor, $client);

    expect($this->service->isImpersonating($this->advisor))->toBeTrue()
        ->and($this->service->getImpersonatedClientId($this->advisor))->toBe($client->id);
});

it('rejects entering unassigned client', function () {
    $unassignedClient = User::factory()->create();

    expect(fn () => $this->service->enterClientProfile($this->advisor, $unassignedClient))
        ->toThrow(HttpException::class, 'Client is not assigned to you');
});

it('rejects entering admin user', function () {
    $adminClient = User::factory()->create(['is_admin' => true]);
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $adminClient->id,
        'status' => 'active',
    ]);

    expect(fn () => $this->service->enterClientProfile($this->advisor, $adminClient))
        ->toThrow(HttpException::class, 'Cannot enter an admin account');
});

it('rejects entering another advisor', function () {
    $anotherAdvisor = User::factory()->create(['is_advisor' => true]);
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $anotherAdvisor->id,
        'status' => 'active',
    ]);

    expect(fn () => $this->service->enterClientProfile($this->advisor, $anotherAdvisor))
        ->toThrow(HttpException::class, 'Cannot enter another advisor account');
});

it('rejects nested impersonation', function () {
    $client1 = User::factory()->create();
    $client2 = User::factory()->create();

    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client1->id,
        'status' => 'active',
    ]);
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client2->id,
        'status' => 'active',
    ]);

    $this->service->enterClientProfile($this->advisor, $client1);

    expect(fn () => $this->service->enterClientProfile($this->advisor, $client2))
        ->toThrow(HttpException::class, 'Already impersonating a client');
});

it('logs enter and exit to AuditLog', function () {
    $client = User::factory()->create();
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->service->enterClientProfile($this->advisor, $client);

    $enterLog = AuditLog::where('action', 'enter_client')
        ->where('event_type', 'admin')
        ->latest('created_at')
        ->first();

    expect($enterLog)->not->toBeNull()
        ->and($enterLog->metadata['advisor_id'])->toBe($this->advisor->id)
        ->and($enterLog->metadata['client_id'])->toBe($client->id);

    $this->service->exitClientProfile($this->advisor);

    $exitLog = AuditLog::where('action', 'exit_client')
        ->where('event_type', 'admin')
        ->latest('created_at')
        ->first();

    expect($exitLog)->not->toBeNull()
        ->and($exitLog->metadata['advisor_id'])->toBe($this->advisor->id)
        ->and($exitLog->metadata['client_id'])->toBe($client->id);
});

describe('TransientToken (stateful SPA auth) handling', function () {
    beforeEach(function () {
        $this->advisor->withAccessToken(new TransientToken);
    });

    it('aborts enterClientProfile with 503 when current token is transient', function () {
        $client = User::factory()->create();
        AdvisorClient::factory()->create([
            'advisor_id' => $this->advisor->id,
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        expect(fn () => $this->service->enterClientProfile($this->advisor, $client))
            ->toThrow(HttpException::class, 'Advisor impersonation is not supported under cookie-based SPA authentication. Use a personal access token.');

        expect(Cache::has('advisor_impersonation:'))->toBeFalse()
            ->and(AuditLog::where('action', 'enter_client')->exists())->toBeFalse();
    });

    it('silently no-ops exitClientProfile when current token is transient', function () {
        $this->service->exitClientProfile($this->advisor);

        expect(AuditLog::where('action', 'exit_client')->exists())->toBeFalse();
    });

    it('returns false from isImpersonating when current token is transient', function () {
        expect($this->service->isImpersonating($this->advisor))->toBeFalse();
    });

    it('returns null from getImpersonatedClientId when current token is transient', function () {
        expect($this->service->getImpersonatedClientId($this->advisor))->toBeNull();
    });

    it('does not write empty-suffix cache key when current token is transient', function () {
        $this->service->isImpersonating($this->advisor);
        $this->service->getImpersonatedClientId($this->advisor);
        $this->service->exitClientProfile($this->advisor);

        expect(Cache::has('advisor_impersonation:'))->toBeFalse();
    });
});

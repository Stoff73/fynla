<?php

declare(strict_types=1);

use App\Models\AdvisorClient;
use App\Models\ClientActivity;
use App\Models\User;
use App\Services\Advisor\AdvisorDashboardService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->advisor = User::factory()->create(['is_advisor' => true]);
    $this->service = app(AdvisorDashboardService::class);
});

it('returns correct dashboard stats for advisor', function () {
    // Create 3 active clients
    $clients = [];
    for ($i = 0; $i < 3; $i++) {
        $client = User::factory()->create();
        $clients[] = AdvisorClient::factory()->create([
            'advisor_id' => $this->advisor->id,
            'client_id' => $client->id,
            'status' => 'active',
            'next_review_due' => now()->subDays(5), // overdue
        ]);
    }

    // 1 inactive client (should not count)
    $inactiveClient = User::factory()->create();
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $inactiveClient->id,
        'status' => 'inactive',
        'next_review_due' => now()->subDays(5),
    ]);

    // Activity this week: 2 comms
    ClientActivity::factory()->create([
        'advisor_client_id' => $clients[0]->id,
        'advisor_id' => $this->advisor->id,
        'client_id' => $clients[0]->client_id,
        'activity_type' => 'email',
        'activity_date' => now(),
    ]);
    ClientActivity::factory()->create([
        'advisor_client_id' => $clients[1]->id,
        'advisor_id' => $this->advisor->id,
        'client_id' => $clients[1]->client_id,
        'activity_type' => 'phone',
        'activity_date' => now(),
    ]);

    // Report this month
    ClientActivity::factory()->suitabilityReport()->create([
        'advisor_client_id' => $clients[2]->id,
        'advisor_id' => $this->advisor->id,
        'client_id' => $clients[2]->client_id,
        'activity_date' => now(),
    ]);

    $stats = $this->service->getDashboardStats($this->advisor);

    expect($stats['clients'])->toBe(3)
        ->and($stats['reviewsDue'])->toBe(3)
        ->and($stats['commsThisWeek'])->toBe(3) // 2 comms + 1 report = 3 total activities this week
        ->and($stats['reportsThisMonth'])->toBe(1);
});

it('returns client list with module status', function () {
    $client = User::factory()->create([
        'first_name' => 'Alice',
        'surname' => 'Smith',
    ]);

    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $list = $this->service->getClientList($this->advisor);

    expect($list)->toHaveCount(1)
        ->and($list[0]['client_id'])->toBe($client->id)
        ->and($list[0]['display_name'])->toBe('Alice Smith')
        ->and($list[0]['module_status'])->toBeArray()
        ->and($list[0]['module_status'])->toHaveKeys(['protection', 'savings', 'investment', 'retirement', 'estate']);
});

it('shows coupled clients as single row with combined name', function () {
    $spouse = User::factory()->create([
        'first_name' => 'Sarah',
        'surname' => 'Mitchell',
    ]);
    $client = User::factory()->create([
        'first_name' => 'David',
        'surname' => 'Mitchell',
        'spouse_id' => $spouse->id,
    ]);

    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $list = $this->service->getClientList($this->advisor);

    expect($list)->toHaveCount(1)
        ->and($list[0]['display_name'])->toBe('David & Sarah Mitchell');
});

it('returns overdue reviews sorted by due date', function () {
    $client1 = User::factory()->create(['first_name' => 'Oldest', 'surname' => 'Overdue']);
    $client2 = User::factory()->create(['first_name' => 'Recent', 'surname' => 'Overdue']);
    $client3 = User::factory()->create(['first_name' => 'Upcoming', 'surname' => 'Review']);

    // Client1: overdue by 60 days
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client1->id,
        'status' => 'active',
        'next_review_due' => now()->subDays(60),
    ]);

    // Client2: overdue by 5 days
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client2->id,
        'status' => 'active',
        'next_review_due' => now()->subDays(5),
    ]);

    // Client3: due in 10 days (within 30-day window)
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client3->id,
        'status' => 'active',
        'next_review_due' => now()->addDays(10),
    ]);

    // Client4: due in 60 days (outside 30-day window, should NOT appear)
    $client4 = User::factory()->create(['first_name' => 'Far', 'surname' => 'Future']);
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client4->id,
        'status' => 'active',
        'next_review_due' => now()->addDays(60),
    ]);

    $reviews = $this->service->getReviewsDue($this->advisor);

    expect($reviews)->toHaveCount(3)
        ->and($reviews[0]['display_name'])->toBe('Oldest Overdue')
        ->and($reviews[0]['is_overdue'])->toBeTrue()
        ->and($reviews[0]['days_overdue'])->toBeGreaterThanOrEqual(59)
        ->and($reviews[1]['display_name'])->toBe('Recent Overdue')
        ->and($reviews[1]['is_overdue'])->toBeTrue()
        ->and($reviews[2]['display_name'])->toBe('Upcoming Review')
        ->and($reviews[2]['is_overdue'])->toBeFalse()
        ->and($reviews[2]['days_until_due'])->toBeGreaterThanOrEqual(9);
});

it('returns recent activity feed limited by count', function () {
    $client = User::factory()->create();
    $ac = AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    // Create 5 activities
    for ($i = 0; $i < 5; $i++) {
        ClientActivity::factory()->create([
            'advisor_client_id' => $ac->id,
            'advisor_id' => $this->advisor->id,
            'client_id' => $client->id,
            'activity_date' => now()->subDays($i),
        ]);
    }

    $activities = $this->service->getRecentActivity($this->advisor, 3);

    expect($activities)->toHaveCount(3)
        ->and($activities[0])->toHaveKeys([
            'id', 'client_id', 'client_name', 'activity_type',
            'summary', 'activity_date', 'report_type',
            'follow_up_date', 'follow_up_completed',
        ]);
});

it('caches client list for 5 minutes', function () {
    $client = User::factory()->create(['first_name' => 'Cached', 'surname' => 'Client']);
    AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    // First call populates cache
    $list1 = $this->service->getClientList($this->advisor);

    expect(Cache::has("advisor:{$this->advisor->id}:clients"))->toBeTrue();

    // Second call returns cached data
    $list2 = $this->service->getClientList($this->advisor);

    expect($list1)->toBe($list2);
});

<?php

declare(strict_types=1);

use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Models\News\NewsSubscriber;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('confirms a pending subscriber via valid token and queues welcome mail', function () {
    $token = str_repeat('A', 48);
    $subscriber = NewsSubscriber::factory()->create(['confirmation_token' => $token]);

    $response = $this->get("/subscribe/news/confirm/{$token}");

    $response->assertOk();
    $response->assertSee("You're subscribed", false);

    expect($subscriber->fresh()->confirmed_at)->not->toBeNull();
    Mail::assertQueued(NewsletterWelcomeMail::class);
});

it('returns 404 for an invalid confirm token', function () {
    $invalidToken = str_repeat('B', 48);
    $this->get("/subscribe/news/confirm/{$invalidToken}")->assertNotFound();
});

it('is idempotent — second confirm click does not re-queue welcome', function () {
    $token = str_repeat('C', 48);
    NewsSubscriber::factory()->confirmed()->create(['confirmation_token' => $token]);

    $this->get("/subscribe/news/confirm/{$token}")->assertOk();

    Mail::assertNotQueued(NewsletterWelcomeMail::class);
});

it('unsubscribes a confirmed subscriber via valid token', function () {
    $token = str_repeat('D', 48);
    $subscriber = NewsSubscriber::factory()->confirmed()->create(['confirmation_token' => $token]);

    $response = $this->get("/unsubscribe/news/{$token}");

    $response->assertOk();
    $response->assertSee("You've unsubscribed", false);

    expect($subscriber->fresh()->unsubscribed_at)->not->toBeNull();
});

it('returns 404 for an invalid unsubscribe token', function () {
    $invalidToken = str_repeat('Z', 48);
    $this->get("/unsubscribe/news/{$invalidToken}")->assertNotFound();
});

it('is idempotent — second unsubscribe click does not change unsubscribed_at', function () {
    $token = str_repeat('E', 48);
    $subscriber = NewsSubscriber::factory()->unsubscribed()->create(['confirmation_token' => $token]);
    $first = $subscriber->fresh()->unsubscribed_at;

    $this->get("/unsubscribe/news/{$token}")->assertOk();

    expect($subscriber->fresh()->unsubscribed_at->timestamp)->toBe($first->timestamp);
});

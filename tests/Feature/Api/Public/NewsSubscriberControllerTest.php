<?php

declare(strict_types=1);

use App\Mail\Newsletter\NewsletterConfirmationMail;
use App\Models\News\NewsSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('news-subscribe:127.0.0.1');
});

it('creates a pending subscriber and sends confirmation email for a new address', function () {
    $response = $this->postJson('/api/news/subscribe', [
        'email' => 'new@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'pending_confirmation',
        ]);

    $subscriber = NewsSubscriber::where('email', 'new@example.com')->first();
    expect($subscriber)->not->toBeNull();
    expect($subscriber->confirmed_at)->toBeNull();
    expect($subscriber->confirmation_token)->not->toBeEmpty();

    Mail::assertSent(NewsletterConfirmationMail::class, fn ($mail) => $mail->subscriber->email === 'new@example.com');
});

it('returns already_registered when email belongs to a Fynla user', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/news/subscribe', [
        'email' => 'existing@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'already_registered',
        ]);

    expect(NewsSubscriber::where('email', 'existing@example.com')->exists())->toBeFalse();
    Mail::assertNotSent(NewsletterConfirmationMail::class);
});

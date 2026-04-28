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

it('returns already_confirmed when subscriber exists and is confirmed', function () {
    NewsSubscriber::factory()->confirmed()->create(['email' => 'confirmed@example.com']);

    $response = $this->postJson('/api/news/subscribe', [
        'email' => 'confirmed@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'already_confirmed',
        ]);

    Mail::assertNotSent(NewsletterConfirmationMail::class);
});

it('resends the confirmation email when a pending subscriber re-submits', function () {
    $original = NewsSubscriber::factory()->create([
        'email' => 'pending@example.com',
        'confirmation_token' => 'OLDTOKEN',
    ]);

    $response = $this->postJson('/api/news/subscribe', [
        'email' => 'pending@example.com',
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'pending_confirmation']);

    $reloaded = $original->fresh();
    expect($reloaded->confirmation_token)->not->toBe('OLDTOKEN');
    expect($reloaded->confirmed_at)->toBeNull();

    Mail::assertSent(NewsletterConfirmationMail::class, 1);
});

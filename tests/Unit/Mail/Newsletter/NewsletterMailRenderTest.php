<?php

declare(strict_types=1);

use App\Mail\Newsletter\NewsletterConfirmationMail;
use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Models\News\NewsSubscriber;

beforeEach(function () {
    $this->subscriber = NewsSubscriber::factory()->make([
        'email' => 'jane@example.com',
        'confirmation_token' => 'TESTTOKEN123',
    ]);
});

it('renders the confirmation mail with the confirm link', function () {
    $rendered = (new NewsletterConfirmationMail($this->subscriber))->render();

    expect($rendered)->toContain('/subscribe/news/confirm/TESTTOKEN123');
    expect($rendered)->toContain('Confirm subscription');
});

it('renders the welcome mail with the unsubscribe link', function () {
    $rendered = (new NewsletterWelcomeMail($this->subscriber))->render();

    expect($rendered)->toContain('/unsubscribe/news/TESTTOKEN123');
    expect($rendered)->toContain('Read latest news');
});

it('confirmation mail uses the marketing from-address', function () {
    $envelope = (new NewsletterConfirmationMail($this->subscriber))->envelope();

    expect($envelope->from->address)->toBe(config('mail.marketing.address'));
});

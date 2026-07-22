<?php

declare(strict_types=1);

use App\Mail\BugReportMail;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Mail::fake();
    Cache::flush();
});

it('rejects unauthenticated requests', function () {
    $response = $this->postJson('/api/bug-report', [
        'description' => 'Something is broken',
    ]);

    $response->assertUnauthorized();
    Mail::assertNothingQueued();
});

it('accepts a valid bug report from an authenticated user', function () {
    $user = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'Reporter',
        'email' => 'reporter@example.com',
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/bug-report', [
        'description' => 'Pension projection chart fails to load.',
        'expected_behaviour' => 'Should render after fetch.',
        'console_logs' => '[ERROR] TypeError: cannot read property "x" of undefined',
        'page_url' => 'https://fynla.org/retirement',
        'user_agent' => 'Mozilla/5.0',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    Mail::assertQueued(BugReportMail::class, function (BugReportMail $mail) use ($user) {
        return $mail->bugReport['user_id'] === $user->id
            && $mail->bugReport['user_email'] === 'reporter@example.com'
            && $mail->bugReport['description'] === 'Pension projection chart fails to load.';
    });
});

it('rejects console_logs payloads larger than 2 KB', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/bug-report', [
        'description' => 'Some bug',
        'console_logs' => str_repeat('a', 2049),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['console_logs']);
    Mail::assertNothingQueued();
});

it('strips HTML from user-supplied text fields before queuing the mail', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/bug-report', [
        'description' => 'Click <a href="http://evil.example/phish">here</a> to reset',
        'expected_behaviour' => '<script>alert(1)</script>plain',
        'console_logs' => '<b>console</b>',
        'page_url' => '<img src=x>https://fynla.org/x',
        'user_agent' => 'Browser<script>',
    ])->assertOk();

    Mail::assertQueued(BugReportMail::class, function (BugReportMail $mail) {
        return $mail->bugReport['description'] === 'Click here to reset'
            && $mail->bugReport['expected_behaviour'] === 'alert(1)plain'
            && $mail->bugReport['console_logs'] === 'console'
            && $mail->bugReport['page_url'] === 'https://fynla.org/x'
            && $mail->bugReport['user_agent'] === 'Browser';
    });
});

it('requires a description', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/bug-report', [
        'expected_behaviour' => 'No description sent.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['description']);

    Mail::assertNothingQueued();
});

it('attaches the Fyn transcript when reporting from your own conversation', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Hi there. What would you like to look at?',
    ]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Hi there. What would you like to look at?',
    ]);

    $this->postJson('/api/bug-report', [
        'description' => 'Fyn repeated its greeting on startup.',
        'conversation_id' => $conversation->id,
    ])->assertOk();

    Mail::assertQueued(BugReportMail::class, function (BugReportMail $mail) {
        return str_contains((string) $mail->bugReport['fyn_transcript'], 'Fyn: Hi there.')
            && substr_count((string) $mail->bugReport['fyn_transcript'], 'Fyn: Hi there.') === 2;
    });
});

it('never attaches another user\'s conversation (ownership-scoped)', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Private financial detail.',
    ]);

    Sanctum::actingAs($attacker);
    $this->postJson('/api/bug-report', [
        'description' => 'Trying to read someone else\'s chat.',
        'conversation_id' => $conversation->id,
    ])->assertOk();

    Mail::assertQueued(BugReportMail::class, function (BugReportMail $mail) {
        return $mail->bugReport['fyn_transcript'] === null;
    });
});

it('accepts allowlisted native diagnostics without attaching financial conversation text', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My Cash ISA contains £12,000.',
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/bug-report', [
        'description' => 'The native screen did not refresh.',
        'platform' => 'ios',
        'app_version' => '1.0.0',
        'app_build' => '42',
        'environment' => 'staging',
        'request_correlation_id' => 'request-42',
        'native_session_uuid' => '9e7d314a-e607-4b93-b739-6864363cf913',
        'conversation_id' => $conversation->id,
    ])->assertOk();

    Mail::assertQueued(BugReportMail::class, function (BugReportMail $mail) use ($conversation) {
        return $mail->bugReport['app_build'] === '42'
            && $mail->bugReport['environment'] === 'staging'
            && $mail->bugReport['request_correlation_id'] === 'request-42'
            && $mail->bugReport['native_session_uuid'] === '9e7d314a-e607-4b93-b739-6864363cf913'
            && $mail->bugReport['conversation_id'] === $conversation->id
            && $mail->bugReport['fyn_transcript'] === null;
    });
});

it('rate-limits to 5 bug reports per hour per user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/bug-report', [
            'description' => "Report number {$i}",
        ])->assertOk();
    }

    $this->postJson('/api/bug-report', [
        'description' => 'Report number 6',
    ])->assertStatus(429);

    Mail::assertQueued(BugReportMail::class, 5);
});

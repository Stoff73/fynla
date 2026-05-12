<?php

declare(strict_types=1);

use App\Mail\BugReportMail;
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

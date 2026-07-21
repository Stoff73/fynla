<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;

/**
 * Sprint 0.11.6 — `HasAiChat::generateTitle` must strip HTML tags from the
 * incoming first-message before truncating to 100 chars and persisting to
 * `ai_conversations.title`. Without this, an attacker can shove `<script>` /
 * `<img onerror>` / arbitrary markup into the conversation list sidebar by
 * sending it as their first chat message — the title is rendered straight
 * into the Vue sidebar component without per-render escaping in some legacy
 * code paths, and even where escaped it pollutes the audit trail.
 *
 * Spec: April/April24Updates/plan/10-sprint-0-plan.md §S0.11 Step 6.
 */
function invokeGenerateTitle(string $message): string
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'generateTitle');
    $reflection->setAccessible(true);

    return $reflection->invoke($agent, $message);
}

describe('HasAiChat::generateTitle sanitation (S0.11.6)', function () {
    it('strips HTML tags from the title', function () {
        $title = invokeGenerateTitle('<script>alert("xss")</script>Hello world');

        expect($title)->not->toContain('<script>')
            ->and($title)->not->toContain('</script>')
            ->and($title)->toContain('Hello world');
    });

    it('strips img tags including onerror handlers', function () {
        $title = invokeGenerateTitle('<img src=x onerror="fetch(\'//evil\')">Pension question');

        expect($title)->not->toContain('<img')
            ->and($title)->not->toContain('onerror')
            ->and($title)->toContain('Pension question');
    });

    it('caps the title at 100 characters', function () {
        // Plain ASCII, no tags — pure length-cap test
        $message = str_repeat('a', 200);
        $title = invokeGenerateTitle($message);

        // Allow the existing trailing "..." marker but the cleaned slice
        // itself must be ≤100 chars.
        $withoutMarker = rtrim($title, '.');
        expect(mb_strlen($withoutMarker))->toBeLessThanOrEqual(100);
    });

    it('appends an ellipsis marker when the input exceeds the cap', function () {
        $message = str_repeat('a', 200);
        $title = invokeGenerateTitle($message);

        expect($title)->toEndWith('...');
    });

    it('does not append an ellipsis when the input fits in the cap', function () {
        $title = invokeGenerateTitle('Short pension query');

        expect($title)->not->toEndWith('...')
            ->and($title)->toBe('Short pension query');
    });

    it('leaves no markup characters in the title', function () {
        // strip_tags removes the tag markup but preserves the inner text
        // (so `<script>alert(1)</script>` collapses to the plain text
        // `alert(1)`). The contract is "no `<` / `>` ever survives" —
        // not "always non-empty" — because the security concern is
        // markup leaking into the sidebar render, not the residual text.
        $title = invokeGenerateTitle('<script>alert(1)</script>');

        expect($title)->not->toContain('<')
            ->and($title)->not->toContain('>');
    });

    it('preserves multibyte content within the 100-char cap', function () {
        $title = invokeGenerateTitle('Pension transfer — should I move my SIPP?');

        expect($title)->toContain('Pension transfer')
            ->and($title)->toContain('SIPP');
    });
});

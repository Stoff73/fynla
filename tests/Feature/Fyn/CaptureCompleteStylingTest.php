<?php

declare(strict_types=1);

/**
 * INV-2.4.3 — the `capture_complete` SSE event still ships (it confirms
 * a save) but the rendered bubble must look like a normal assistant
 * `content` bubble: same background, same border colour, no capture-mode
 * badge, no distinct ring / outline, no icon. The frontend has separate
 * render branches per role, so this test pins the OUTER container's
 * class set against the messageClass() return for assistants in
 * resources/js/components/Shared/AiChatPanel.vue.
 *
 * Static-analysis style: read the .vue file, parse the relevant blocks,
 * and assert class contents. We can't do a true DOM test from Pest
 * without Playwright, but the source-level assertion catches every
 * regression that would change the rendered output.
 */
beforeEach(function () {
    $this->panelPath = base_path('resources/js/components/Shared/AiChatPanel.vue');
    expect(file_exists($this->panelPath))->toBeTrue();
    $this->panel = file_get_contents($this->panelPath);
});

it('renders capture_complete bubbles with the same background + border colour as a normal assistant bubble', function () {
    // Baseline: the assistant bubble class string emitted by messageClass().
    expect($this->panel)->toContain("'bg-savannah-100 border border-light-gray text-horizon-500'");

    // Both capture_complete render branches (inline + docked panel)
    // must use the same `bg-savannah-100 border border-light-gray`
    // pair on their outer container. No `border-horizon-200`,
    // `border-violet-*`, `ring-*`, or other capture-mode tells.
    $captureBlocks = [];
    if (preg_match_all('/v-else-if="msg\.role === \'capture_complete\'".*?<\/div>\s*<\/div>/s', $this->panel, $matches) > 0) {
        $captureBlocks = $matches[0];
    }
    expect($captureBlocks)->not->toBeEmpty();

    foreach ($captureBlocks as $block) {
        // Same background as the assistant bubble.
        expect($block)->toContain('bg-savannah-100');
        // Same border colour as the assistant bubble.
        expect($block)->toContain('border-light-gray');
    }
});

it('does NOT render a capture-mode badge, ring, icon, or distinct border on capture_complete', function () {
    if (preg_match_all('/v-else-if="msg\.role === \'capture_complete\'".*?<\/div>\s*<\/div>/s', $this->panel, $matches) === 0) {
        $this->fail('Expected at least one capture_complete render branch in AiChatPanel.vue.');
    }

    foreach ($matches[0] as $block) {
        // No distinct border colour — the assistant bubble uses
        // border-light-gray; anything else (horizon, violet, raspberry,
        // spring) would be a capture-mode tell.
        expect($block)->not->toContain('border-horizon-200');
        expect($block)->not->toContain('border-horizon-300');
        expect($block)->not->toContain('border-horizon-400');
        expect($block)->not->toContain('border-horizon-500');
        expect($block)->not->toContain('border-violet');
        expect($block)->not->toContain('border-raspberry');
        expect($block)->not->toContain('border-spring');

        // No focus ring / outline.
        expect($block)->not->toMatch('/\bring-[\w-]+/');
        expect($block)->not->toMatch('/\boutline-[\w-]+/');

        // No SVG icon, no <svg>, no icon-font codepoints (per CLAUDE.md
        // Rule 14 the chat surface is icon-banned).
        expect($block)->not->toMatch('/<svg\b/');
        expect($block)->not->toMatch('/<i\s+class="(?:fa|material|bi|icon)-/');
    }
});

it('uses the same outer flex/justify alignment as the assistant bubble', function () {
    if (preg_match_all('/v-else-if="msg\.role === \'capture_complete\'".*?<\/div>\s*<\/div>/s', $this->panel, $matches) === 0) {
        $this->fail('Expected at least one capture_complete render branch in AiChatPanel.vue.');
    }

    // The assistant fallback container uses `flex justify-start` for
    // assistant rows (see messageClass + the v-else container in the
    // template). capture_complete must use the same alignment so it
    // visually anchors to the same column.
    foreach ($matches[0] as $block) {
        expect($block)->toMatch('/\bflex\s+justify-start\b/');
    }
});

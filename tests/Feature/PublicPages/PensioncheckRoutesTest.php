<?php

declare(strict_types=1);

/**
 * Smoke tests for the /pensioncheck public funnel routes (Task B3).
 *
 * Asserts:
 *  - Both routes return HTTP 200 with the correct page content.
 *  - /pensioncheck is NOT served by the SPA catch-all (no id="app" mount node).
 *  - /pensioncheck/plan embeds the PENSIONCHECK_ESTIMATE JS variable and the
 *    register card.
 */
it('returns 200 with the employment question on /pensioncheck', function () {
    $response = $this->get('/pensioncheck');

    $response->assertStatus(200);
    $response->assertSee('What is your employment status?', false);
});

it('is not served by the SPA catch-all on /pensioncheck', function () {
    $response = $this->get('/pensioncheck');

    $response->assertStatus(200);
    $response->assertDontSee('id="app"', false);
});

it('returns 200 with the register card and estimate on /pensioncheck/plan', function () {
    $response = $this->get('/pensioncheck/plan');

    $response->assertStatus(200);
    $response->assertSee('Create your free account', false);
    $response->assertSee('window.PENSIONCHECK_ESTIMATE', false);
});

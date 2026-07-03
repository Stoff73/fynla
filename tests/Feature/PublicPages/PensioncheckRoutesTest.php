<?php

declare(strict_types=1);

namespace Tests\Feature\PublicPages;

use Tests\TestCase;

/**
 * Smoke tests for the /pensioncheck public funnel routes (Task B3).
 *
 * Asserts:
 *  - Both routes return HTTP 200 with the correct page content.
 *  - /pensioncheck is NOT served by the SPA catch-all (no id="app" mount node).
 *  - /pensioncheck/plan embeds the PENSIONCHECK_ESTIMATE JS variable and the
 *    register card.
 */
class PensioncheckRoutesTest extends TestCase
{
    public function test_pensioncheck_funnel_returns_200_with_employment_question(): void
    {
        $response = $this->get('/pensioncheck');

        $response->assertStatus(200);
        $response->assertSee('What is your employment status?', false);
    }

    public function test_pensioncheck_is_not_served_by_spa_catch_all(): void
    {
        $response = $this->get('/pensioncheck');

        $response->assertStatus(200);
        $response->assertDontSee('id="app"', false);
    }

    public function test_pensioncheck_plan_returns_200_with_register_card_and_estimate(): void
    {
        $response = $this->get('/pensioncheck/plan');

        $response->assertStatus(200);
        $response->assertSee('Create your free account', false);
        $response->assertSee('window.PENSIONCHECK_ESTIMATE', false);
    }
}

<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Drop any authentication bound earlier in the test (e.g. a
     * Sanctum::actingAs() in beforeEach) so the next request runs as a guest.
     *
     * This exists to replace the old `$this->app = $this->createApplication();`
     * pattern in "requires authentication" tests. Replacing the application
     * instance silently opened a SECOND database connection outside
     * RefreshDatabase's transaction, so any factory write made after the swap
     * was committed permanently to the test database — a test-isolation leak
     * that seeded random users_email_unique faker collisions in later tests.
     *
     * Sanctum::actingAs() only does guard($g)->setUser($user) + shouldUse($g);
     * forgetting the instantiated guards makes the next request re-resolve the
     * guard from the (absent) bearer token, yielding a genuine 401 while every
     * DB write stays inside the test transaction.
     */
    protected function actingAsGuest(): void
    {
        app('auth')->forgetGuards();
    }
}

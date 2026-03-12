<?php

declare(strict_types=1);

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('SendPolicyRenewalReminders', function () {
    it('runs successfully', function () {
        $this->artisan('notifications:policy-renewals')
            ->assertExitCode(0);
    });
});

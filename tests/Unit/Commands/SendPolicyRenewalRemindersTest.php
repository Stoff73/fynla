<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('SendPolicyRenewalReminders', function () {
    it('runs successfully', function () {
        $this->artisan('notifications:policy-renewals')
            ->assertExitCode(0);
    });
});

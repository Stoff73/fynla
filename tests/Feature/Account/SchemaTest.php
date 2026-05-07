<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('users table has deletion tracking columns', function () {
    expect(Schema::hasColumn('users', 'deletion_scheduled_for'))->toBeTrue();
    expect(Schema::hasColumn('users', 'deletion_reason'))->toBeTrue();
    expect(Schema::hasColumn('users', 'deletion_source'))->toBeTrue();
    expect(Schema::hasColumn('users', 'restored_at'))->toBeTrue();
    expect(Schema::hasColumn('users', 'purge_eligible_at'))->toBeTrue();
});

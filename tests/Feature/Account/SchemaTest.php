<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('users table has deletion tracking columns', function () {
    expect(Schema::hasColumn('users', 'deletion_scheduled_for'))->toBeTrue();
    expect(Schema::hasColumn('users', 'deletion_reason'))->toBeTrue();
    expect(Schema::hasColumn('users', 'deletion_source'))->toBeTrue();
    expect(Schema::hasColumn('users', 'restored_at'))->toBeTrue();
    expect(Schema::hasColumn('users', 'purge_eligible_at'))->toBeTrue();
});

it('life_events.joint_owner_id FK is set null on delete', function () {
    $row = DB::selectOne(
        "SELECT r.DELETE_RULE
         FROM information_schema.KEY_COLUMN_USAGE k
         JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
          AND k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA
         WHERE k.TABLE_NAME = 'life_events'
           AND k.COLUMN_NAME = 'joint_owner_id'
           AND k.CONSTRAINT_SCHEMA = DATABASE()"
    );
    expect($row->DELETE_RULE)->toBe('SET NULL');
});

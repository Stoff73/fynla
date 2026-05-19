<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('adds a nullable tier column defaulting to null', function () {
    expect(Schema::hasColumn('users', 'tier'))->toBeTrue();
    $u = User::factory()->create();
    expect($u->fresh()->tier)->toBeNull(); // resolver, not the column, decides
});

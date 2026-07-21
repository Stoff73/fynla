<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('adds a nullable tier column defaulting to null', function () {
    expect(Schema::hasColumn('users', 'tier'))->toBeTrue();
    $u = User::factory()->create();
    expect($u->fresh()->tier)->toBeNull(); // resolver, not the column, decides
});

it('accepts the canonical user tier identities', function (string $tier) {
    expect(User::factory()->create(['tier' => $tier])->fresh()->tier)->toBe($tier);
})->with(['free', 'premium']);

it('rejects retired user tier identities', function (string $tier) {
    expect(fn () => User::factory()->create(['tier' => $tier]))
        ->toThrow(QueryException::class);
})->with(['tier1', 'tier2', 'tier3']);

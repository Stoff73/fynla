<?php

declare(strict_types=1);

use App\Services\Gamification\LevelUpCollector;

it('records nothing by default', function () {
    $c = new LevelUpCollector;
    expect($c->hasLevelUp())->toBeFalse();
    expect($c->highest())->toBeNull();
});

it('keeps only the highest level reached this request', function () {
    $c = new LevelUpCollector;
    $c->record(3, 'Builder');
    $c->record(5, 'Planner');
    $c->record(4, 'Organiser');

    expect($c->hasLevelUp())->toBeTrue();
    expect($c->highest())->toMatchArray(['level' => 5, 'level_name' => 'Planner']);
});

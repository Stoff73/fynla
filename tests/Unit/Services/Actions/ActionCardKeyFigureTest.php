<?php

declare(strict_types=1);

use App\Services\Actions\ActionCardService;

/*
 * Review C1: an estate action's benefit is a one-off Inheritance Tax saving
 * (EstateRecommendationAdapter: "a one-off lump, not an annual income-tax
 * saving"). It must never read "£X a year".
 */
it('labels an annual tax saving per year and an Inheritance Tax saving as one-off', function () {
    expect(ActionCardService::keyFigureFor('tax', 1920))->toBe(['label' => 'Saves about', 'value' => '£1,920 a year', 'sub' => null])
        ->and(ActionCardService::keyFigureFor('estate', 140000))->toBe(['label' => 'Could reduce Inheritance Tax by about', 'value' => '£140,000', 'sub' => null])
        ->and(ActionCardService::keyFigureFor('estate', 140000)['value'])->not->toContain('a year')
        ->and(ActionCardService::keyFigureFor('savings', 0))->toBeNull()
        ->and(ActionCardService::keyFigureFor('savings', null))->toBeNull();
});

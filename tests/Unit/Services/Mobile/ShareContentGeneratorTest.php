<?php

declare(strict_types=1);

use App\Services\Mobile\ShareContentGenerator;

describe('ShareContentGenerator', function () {
    it('generates content for all valid types', function () {
        $generator = new ShareContentGenerator;

        $types = ['goal_milestone', 'net_worth_milestone', 'fyn_insight', 'app_referral'];

        foreach ($types as $type) {
            $content = $generator->generate($type);

            expect($content)->toHaveKeys(['title', 'text', 'url'])
                ->and($content['title'])->toBeString()->not->toBeEmpty()
                ->and($content['text'])->toBeString()->not->toBeEmpty()
                ->and($content['url'])->toContain('fynla.org');
        }
    });

    it('throws for invalid type', function () {
        $generator = new ShareContentGenerator;

        expect(fn () => $generator->generate('invalid'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('never includes currency symbols in any content', function () {
        $generator = new ShareContentGenerator;

        $types = ['goal_milestone', 'net_worth_milestone', 'fyn_insight', 'app_referral'];

        foreach ($types as $type) {
            $content = $generator->generate($type);

            expect($content['text'])->not->toContain('£')
                ->and($content['text'])->not->toContain('$')
                ->and($content['title'])->not->toContain('£');
        }
    });
});

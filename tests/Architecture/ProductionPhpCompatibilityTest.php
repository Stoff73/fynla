<?php

declare(strict_types=1);

use PhpParser\Node\Stmt\ClassConst;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

it('keeps application syntax compatible with the deployed PHP 8.2 runtime', function () {
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $nodeFinder = new NodeFinder;
    $incompatibleConstants = [];

    $files = Finder::create()
        ->files()
        ->name('*.php')
        ->in(dirname(__DIR__, 2).'/app');

    foreach ($files as $file) {
        $statements = $parser->parse($file->getContents()) ?? [];

        foreach ($nodeFinder->findInstanceOf($statements, ClassConst::class) as $constant) {
            if ($constant->type === null) {
                continue;
            }

            $incompatibleConstants[] = $file->getRelativePathname().':'.$constant->getStartLine();
        }
    }

    expect($incompatibleConstants)
        ->toBeEmpty('Typed class constants require PHP 8.3, but csjones.co deploys PHP 8.2.');
});

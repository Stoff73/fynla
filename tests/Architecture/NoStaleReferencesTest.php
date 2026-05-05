<?php

declare(strict_types=1);

/**
 * No Stale References Architecture Test (Sprint 0 Task 0.2)
 *
 * Guards against resurrection of the deleted Python agent sidecar, its
 * Laravel controller / middleware, and the stale OpenAI config block.
 *
 * Per Sprint 0 plan S0.2: scan app/, config/, routes/ for any reference to
 * AgentInternalController, AgentTokenAuth, AGENT_INTERNAL_TOKEN, or
 * OPENAI_CHAT_MODEL. Any match is a violation.
 */
$projectRoot = realpath(__DIR__.'/../../');

function collectStaleReferences(string $directory, string $pattern): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $matches = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $lineNum => $line) {
            if (preg_match($pattern, $line)) {
                $matches[] = $file->getPathname().':'.($lineNum + 1).' — '.trim($line);
            }
        }
    }

    return $matches;
}

describe('No stale references to deleted sidecar / OpenAI config', function () use ($projectRoot) {

    it('has no references to AgentInternalController in app/, config/, or routes/', function () use ($projectRoot) {
        $pattern = '/AgentInternalController/';
        $matches = array_merge(
            collectStaleReferences($projectRoot.'/app', $pattern),
            collectStaleReferences($projectRoot.'/config', $pattern),
            collectStaleReferences($projectRoot.'/routes', $pattern),
        );
        expect($matches)->toBeEmpty(
            'Found stale AgentInternalController references:'.PHP_EOL.implode(PHP_EOL, $matches)
        );
    });

    it('has no references to AgentTokenAuth in app/, config/, or routes/', function () use ($projectRoot) {
        $pattern = '/AgentTokenAuth/';
        $matches = array_merge(
            collectStaleReferences($projectRoot.'/app', $pattern),
            collectStaleReferences($projectRoot.'/config', $pattern),
            collectStaleReferences($projectRoot.'/routes', $pattern),
        );
        expect($matches)->toBeEmpty(
            'Found stale AgentTokenAuth references:'.PHP_EOL.implode(PHP_EOL, $matches)
        );
    });

    it('has no references to AGENT_INTERNAL_TOKEN in app/, config/, or routes/', function () use ($projectRoot) {
        $pattern = '/AGENT_INTERNAL_TOKEN/';
        $matches = array_merge(
            collectStaleReferences($projectRoot.'/app', $pattern),
            collectStaleReferences($projectRoot.'/config', $pattern),
            collectStaleReferences($projectRoot.'/routes', $pattern),
        );
        expect($matches)->toBeEmpty(
            'Found stale AGENT_INTERNAL_TOKEN references:'.PHP_EOL.implode(PHP_EOL, $matches)
        );
    });

    it('has no references to OPENAI_CHAT_MODEL in app/, config/, or routes/', function () use ($projectRoot) {
        $pattern = '/OPENAI_CHAT_MODEL/';
        $matches = array_merge(
            collectStaleReferences($projectRoot.'/app', $pattern),
            collectStaleReferences($projectRoot.'/config', $pattern),
            collectStaleReferences($projectRoot.'/routes', $pattern),
        );
        expect($matches)->toBeEmpty(
            'Found stale OPENAI_CHAT_MODEL references:'.PHP_EOL.implode(PHP_EOL, $matches)
        );
    });

    it('has no /api/internal/agent/ route prefix in routes/', function () use ($projectRoot) {
        $pattern = '#internal/agent#';
        $matches = collectStaleReferences($projectRoot.'/routes', $pattern);
        expect($matches)->toBeEmpty(
            'Found stale /api/internal/agent route references:'.PHP_EOL.implode(PHP_EOL, $matches)
        );
    });
});

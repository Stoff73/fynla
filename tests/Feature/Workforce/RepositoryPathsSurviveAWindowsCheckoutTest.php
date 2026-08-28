<?php

declare(strict_types=1);

/**
 * No tracked path may contain a character Windows forbids in a filename.
 *
 * Two PNGs under `August/bugs/` carried a colon. NTFS reserves `:` for alternate
 * data streams, so git cannot write those entries — and it does not fail cleanly:
 * it **aborts partway through the index rebuild**, leaving a tree that is quietly
 * missing files that were never near the colon.
 *
 * Two `dev` merges dropped `CLAUDE.md` and `CSJTODO.md` that way, and the second
 * would have applied 1,265 deletions to `dev` had it not been caught by hand. The
 * dangerous part is that every unrestricted query reports success — `git status`
 * says the tree is clean, `git ls-files` says the file is tracked, and only a
 * path-scoped `git ls-tree HEAD <path>` reveals the commit does not contain it.
 *
 * So the check has to live where a lossy merge cannot hide it. It is deliberately
 * a pattern over `git ls-files` rather than anything clever: what makes it work is
 * that it runs, not that it is thorough.
 */
it('tracks no path that Windows cannot check out', function () {
    exec('git -C '.escapeshellarg(base_path()).' ls-files 2>/dev/null', $paths, $status);

    expect($status)->toBe(0, 'git ls-files did not run; the check proved nothing.');
    expect($paths)->not->toBeEmpty();

    // `< > : " | ? *` are illegal in an NTFS filename. `/` is the separator and `\`
    // is Windows' own, so neither can appear inside a git path component anyway.
    $illegal = preg_grep('/[<>:"|?*]/', $paths);

    expect(array_values($illegal))->toBe(
        [],
        'A tracked path contains a character Windows forbids. Rename it with '
        .'`git mv` — until then every merge into a Windows worktree aborts the '
        .'index rebuild and silently drops unrelated tracked files.'
    );
});

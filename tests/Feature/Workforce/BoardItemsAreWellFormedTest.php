<?php

declare(strict_types=1);

/**
 * Every board item must still be a board item.
 *
 * On 2026-08-24 a single coordinator edit truncated **three** items to the note it
 * meant to append — W-0349, W-0466 and W-0467 lost their frontmatter, their Intent,
 * their acceptance criteria and two `quality-lead` certification stamps. The cause
 * was a Python one-liner whose arguments evaluate left to right:
 *
 *     open(p, 'w').write(open(p).read() + note)
 *
 * `open(p, 'w')` truncates the file **before** `open(p).read()` runs, so the read
 * returns an empty string and only the new text survives. It looks exactly like an
 * append and is the opposite of one.
 *
 * **Why this needs a test rather than more care.** Two of the three were gated on
 * `compliance-lead`, and a gate is discharged against acceptance criteria — which no
 * longer existed. A reviewer opening the item would have found a note about an
 * unsubscribe route and no statement of what they were being asked to approve.
 * Nothing in the repository would have caught it: no test, no hook, no sweep. It was
 * found by a reviewer trying to stamp a verdict and discovering there was no
 * frontmatter to stamp.
 *
 * The check is deliberately shallow. It does not police content — content is the
 * coordinator's — only that each file is still shaped like a work item, which is the
 * property an accidental overwrite destroys.
 */
it('keeps every board item parseable as a work item', function () {
    $dir = base_path('workforce/ops/board');

    expect(is_dir($dir))->toBeTrue();

    $files = glob($dir.'/W-*.md');

    expect($files)->not->toBeEmpty();

    $malformed = [];

    foreach ($files as $file) {
        $name = basename($file);
        $body = (string) file_get_contents($file);

        // Frontmatter: opens on `---` and closes on a later `---`.
        if (! str_starts_with($body, "---\n")) {
            $malformed[$name] = 'does not open with frontmatter';

            continue;
        }

        $end = strpos($body, "\n---\n", 4);

        if ($end === false) {
            $malformed[$name] = 'frontmatter is never closed';

            continue;
        }

        $frontmatter = substr($body, 4, $end - 4);

        // The four fields every gate, sweep and board page reads.
        foreach (['id', 'title', 'status'] as $key) {
            if (preg_match('/^'.$key.':\s*\S/m', $frontmatter) !== 1) {
                $malformed[$name] = "frontmatter has no `{$key}`";

                continue 2;
            }
        }

        // `id` must match the filename, or a stamp lands on the wrong item.
        preg_match('/^id:\s*(\S+)/m', $frontmatter, $m);

        if (! str_starts_with($name, $m[1] ?? '~')) {
            $malformed[$name] = "id `{$m[1]}` does not match the filename";

            continue;
        }

        // An item with frontmatter and no body is the other truncation shape. The
        // overwrite that prompted this test left only the appended notes and no
        // head, which the frontmatter checks above catch; a partial one could
        // leave only the head, which this catches.
        //
        // Checked as "has a body" rather than "has `## Intent`", because five of
        // the 272 items predate that convention and carry a terse prose body
        // instead — they are well-formed, and a test that reddened on them would
        // be policing a convention rather than an invariant.
        if (strlen(trim(substr($body, $end + 5))) < 40) {
            $malformed[$name] = 'has frontmatter but effectively no body';
        }
    }

    expect($malformed)->toBe(
        [],
        'Board items are malformed. An accidental overwrite is the usual cause — '
        .'recover with `git show <commit>:<path>`, do not rewrite from memory.'
    );
});

/**
 * Two items must never share an id.
 *
 * The sibling test above pins each `id` to its own filename, which is a different
 * property: two files can each match their own name and still both claim `W-0489`.
 * That is what kept happening — three collisions in the 2026-08-26 session alone,
 * because ids are assigned concurrently on `dev` and on working branches with
 * nothing reserving them. The merge then discards one side's rename while both
 * copies sit unchanged at their old paths, and a gate stamped against "W-0489"
 * lands on whichever file the reader opened.
 *
 * Checked here rather than solved with a reservation mechanism: the collisions are
 * cheap to fix and expensive only when they survive a merge unnoticed, so what is
 * missing is the noticing.
 */
it('gives every board item a unique id', function () {
    $files = glob(base_path('workforce/ops/board').'/W-*.md');

    expect($files)->not->toBeEmpty();

    $seen = [];

    foreach ($files as $file) {
        if (preg_match('/^id:\s*(\S+)/m', (string) file_get_contents($file), $m) === 1) {
            $seen[$m[1]][] = basename($file);
        }
    }

    $collisions = array_filter($seen, fn (array $names): bool => count($names) > 1);

    expect($collisions)->toBe(
        [],
        'Two board items claim the same id. Renumber the newer one to the next free '
        .'id and update its cross-references one at a time — a sweep will rewrite '
        .'legitimate citations of the older item.'
    );
});

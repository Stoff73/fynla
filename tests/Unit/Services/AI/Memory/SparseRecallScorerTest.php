<?php

declare(strict_types=1);

use App\Services\AI\Memory\Recall\SparseRecallScorer;

it('ranks episodes by distinct query-term matches, most relevant first', function () {
    $scorer = new SparseRecallScorer;

    $episodes = [
        ['body' => 'User discussed emergency fund and cash savings.', 'path' => 'a'],
        ['body' => 'User wants to retire at 60 with a SIPP pension.', 'path' => 'b'],
        ['body' => 'General chat about the weather.', 'path' => 'c'],
    ];

    $ranked = $scorer->rank('tell me about my pension and retirement', $episodes);

    expect($ranked[0]['path'])->toBe('b'); // pension + retire(ment) match
});

it('returns episodes unchanged in input order when query has no content tokens', function () {
    $scorer = new SparseRecallScorer;
    $episodes = [['body' => 'one', 'path' => 'a'], ['body' => 'two', 'path' => 'b']];

    expect($scorer->rank('the a of', $episodes))->toBe($episodes);
});

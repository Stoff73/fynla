<?php

declare(strict_types=1);

use App\Services\Documents\HTMLBodySanitiser;

beforeEach(function () {
    $this->sanitiser = new HTMLBodySanitiser();
});

it('preserves headings, paragraphs, lists, links and images from our storage path', function () {
    $html = '<h1>Title</h1><p>Body</p><ul><li>One</li></ul>'
        .'<a href="https://example.com">Link</a>'
        .'<img src="/storage/document-articles/12/img-0.png" alt="x">';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->toContain('<h1>Title</h1>')
        ->and($clean)->toContain('<p>Body</p>')
        ->and($clean)->toContain('<ul><li>One</li></ul>')
        ->and($clean)->toContain('<a href="https://example.com"')
        ->and($clean)->toContain('<img src="/storage/document-articles/12/img-0.png"');
});

it('preserves tables', function () {
    $html = '<table><thead><tr><th>A</th><th>B</th></tr></thead>'
        .'<tbody><tr><td>1</td><td>2</td></tr></tbody></table>';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->toContain('<table>')
        ->and($clean)->toContain('<thead>')
        ->and($clean)->toContain('<tbody>')
        ->and($clean)->toContain('<th>A</th>')
        ->and($clean)->toContain('<td>1</td>');
});

it('strips script tags', function () {
    $html = '<p>Hi</p><script>alert(1)</script>';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('alert');
});

it('strips iframes and object tags', function () {
    $html = '<iframe src="x"></iframe><object data="x"></object><embed src="x">';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('<iframe')
        ->and($clean)->not->toContain('<object')
        ->and($clean)->not->toContain('<embed');
});

it('strips on* event handlers', function () {
    $html = '<p onclick="alert(1)">Hi</p><a href="https://x" onmouseover="alert(2)">x</a>';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('onmouseover');
});

it('strips javascript: URLs', function () {
    $html = '<a href="javascript:alert(1)">x</a>';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('javascript:');
});

it('strips img tags whose src does not start with /storage/document-articles/', function () {
    $html = '<img src="https://attacker.example/track.gif" alt="">'
        .'<img src="/storage/elsewhere/img.png" alt="">'
        .'<img src="/storage/document-articles/5/img-0.png" alt="ok">';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('attacker.example')
        ->and($clean)->not->toContain('/storage/elsewhere/')
        ->and($clean)->toContain('/storage/document-articles/5/img-0.png');
});

it('preserves data-pending-image attribute on img placeholders', function () {
    $html = '<img data-pending-image="0" alt="">';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->toContain('data-pending-image="0"');
});

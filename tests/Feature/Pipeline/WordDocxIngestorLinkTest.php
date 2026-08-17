<?php

declare(strict_types=1);

use App\Services\Pipeline\Content\WordDocxIngestor;
use Illuminate\Support\Facades\Config;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Builds real .docx files containing hyperlinks and runs them through the
 * ingestor. Exercising the writer/reader round-trip matters: the bug this
 * covers was that PhpWord's Link has no getTarget(), so the anchor branch
 * never fired and every link in every imported article was flattened.
 */
function docxWithLinks(array $links, string $lead = 'Read more here.'): string
{
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText($lead);

    foreach ($links as $label => $href) {
        $run = $section->addTextRun();
        $run->addText('See ');
        $run->addLink($href, $label);
    }

    $path = tempnam(sys_get_temp_dir(), 'fynla_links_').'.docx';
    IOFactory::createWriter($word, 'Word2007')->save($path);

    return $path;
}

function htmlFrom(string $path): string
{
    $blocks = app(WordDocxIngestor::class)->ingest($path)['blocks'];
    @unlink($path);

    return implode("\n", array_map(
        fn (array $b) => $b['html'] ?? ($b['text'] ?? ''),
        $blocks
    ));
}

beforeEach(function () {
    Config::set('app.url', 'https://fynla.org');
});

it('carries an external hyperlink through as an absolute link', function () {
    $html = htmlFrom(docxWithLinks(['HMRC guidance' => 'https://www.gov.uk/capital-gains-tax']));

    expect($html)->toContain('<a href="https://www.gov.uk/capital-gains-tax">HMRC guidance</a>');
});

it('rewrites a link to the Fynla site as site-relative', function () {
    $html = htmlFrom(docxWithLinks(['our guide' => 'https://fynla.org/insights/pension-basics']));

    expect($html)->toContain('<a href="/insights/pension-basics">our guide</a>')
        ->and($html)->not->toContain('https://fynla.org');
});

it('treats the www subdomain as the Fynla site too', function () {
    $html = htmlFrom(docxWithLinks(['home' => 'https://www.fynla.org/about']));

    expect($html)->toContain('<a href="/about">home</a>');
});

it('keeps the query string and fragment when going relative', function () {
    $html = htmlFrom(docxWithLinks(['calculator' => 'https://fynla.org/tools?mode=iht#results']));

    expect($html)->toContain('<a href="/tools?mode=iht#results">calculator</a>');
});

it('reduces a bare Fynla homepage link to a single slash', function () {
    $html = htmlFrom(docxWithLinks(['Fynla' => 'https://fynla.org']));

    expect($html)->toContain('<a href="/">Fynla</a>');
});

it('leaves mailto links alone', function () {
    $html = htmlFrom(docxWithLinks(['email us' => 'mailto:hello@fynla.org']));

    expect($html)->toContain('<a href="mailto:hello@fynla.org">email us</a>');
});

it('does not mistake a lookalike domain for the Fynla site', function () {
    $html = htmlFrom(docxWithLinks(['not us' => 'https://fynla.org.uk/insights/foo']));

    expect($html)->toContain('<a href="https://fynla.org.uk/insights/foo">not us</a>');
});

it('rewrites links to whichever environment the article is authored on', function () {
    Config::set('app.url', 'https://csjones.co/fynla');

    $html = htmlFrom(docxWithLinks(['dev copy' => 'https://csjones.co/fynla/insights/foo']));

    expect($html)->toContain('<a href="/fynla/insights/foo">dev copy</a>');
});

it('escapes a hostile href rather than emitting it raw', function () {
    $html = htmlFrom(docxWithLinks(['click' => 'https://evil.test/a"onmouseover="alert(1)']));

    expect($html)->not->toContain('onmouseover="alert')
        ->and($html)->toContain('&quot;');
});

<?php

declare(strict_types=1);

use App\Services\Insights\BlockValidator;
use App\Services\Pipeline\Content\WordDocxIngestor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;

/**
 * Word stores a list item as an ordinary paragraph carrying a numbering id; the
 * bullets-versus-numbers decision lives in word/numbering.xml. These tests drive
 * real .docx files through the ingestor to prove both survive as list blocks in
 * the shape ListBlock.vue and BlockValidator expect (`ordered`, `items`).
 */
function ingestBlocks(callable $build): array
{
    $word = new PhpWord;
    $build($word->addSection());

    $path = tempnam(sys_get_temp_dir(), 'fynla_lists_').'.docx';
    IOFactory::createWriter($word, 'Word2007')->save($path);

    $blocks = app(WordDocxIngestor::class)->ingest($path)['blocks'];
    @unlink($path);

    return $blocks;
}

function listBlocks(array $blocks): array
{
    return array_values(array_filter($blocks, fn (array $b) => $b['type'] === 'list'));
}

/**
 * PhpWord's writer collapses every list style to bullets, so a round-trip
 * cannot produce a numbered list. Build the package by hand the way Word does:
 * numbering.xml decides bullets versus numbers, and each paragraph points at a
 * numbering definition by id.
 *
 * @param  list<array{text:string,numId:int}>  $items
 */
function handBuiltDocx(array $items): string
{
    $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $pkg = 'http://schemas.openxmlformats.org/package/2006/relationships';
    $od = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    $paragraphs = '';
    foreach ($items as $item) {
        $paragraphs .= '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/>'
            .'<w:numId w:val="'.$item['numId'].'"/></w:numPr></w:pPr>'
            .'<w:r><w:t>'.htmlspecialchars($item['text'], ENT_XML1).'</w:t></w:r></w:p>';
    }

    $lvl = fn (string $fmt) => '<w:lvl w:ilvl="0"><w:numFmt w:val="'.$fmt.'"/></w:lvl>';

    $parts = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            .'</Types>',

        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="'.$pkg.'">'
            .'<Relationship Id="rId1" Type="'.$od.'/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>',

        'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="'.$pkg.'">'
            .'<Relationship Id="rId1" Type="'.$od.'/numbering" Target="numbering.xml"/>'
            .'</Relationships>',

        // numId 1 renders as bullets, numId 2 as decimal numbers.
        'word/numbering.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:numbering xmlns:w="'.$w.'">'
            .'<w:abstractNum w:abstractNumId="10">'.$lvl('bullet').'</w:abstractNum>'
            .'<w:abstractNum w:abstractNumId="20">'.$lvl('decimal').'</w:abstractNum>'
            .'<w:num w:numId="1"><w:abstractNumId w:val="10"/></w:num>'
            .'<w:num w:numId="2"><w:abstractNumId w:val="20"/></w:num>'
            .'</w:numbering>',

        'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="'.$w.'"><w:body>'.$paragraphs.'</w:body></w:document>',
    ];

    $path = tempnam(sys_get_temp_dir(), 'fynla_numbered_').'.docx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($parts as $name => $xml) {
        $zip->addFromString($name, $xml);
    }
    $zip->close();

    return $path;
}

function ingestPath(string $path): array
{
    $blocks = app(WordDocxIngestor::class)->ingest($path)['blocks'];
    @unlink($path);

    return $blocks;
}

it('turns a bulleted list into one unordered list block', function () {
    $blocks = ingestBlocks(function ($s) {
        $s->addText('Intro paragraph.');
        foreach (['Use your ISA allowance', 'Check your pension contributions', 'Review your will'] as $item) {
            $s->addListItem($item, 0, null, ListItemStyle::TYPE_BULLET_FILLED);
        }
    });

    $lists = listBlocks($blocks);

    expect($lists)->toHaveCount(1)
        ->and($lists[0]['ordered'])->toBeFalse()
        ->and($lists[0]['items'])->toBe([
            'Use your ISA allowance',
            'Check your pension contributions',
            'Review your will',
        ]);
});

it('turns a numbered list into an ordered list block', function () {
    $blocks = ingestPath(handBuiltDocx([
        ['text' => 'Gather your paperwork', 'numId' => 2],
        ['text' => 'Work out your allowance', 'numId' => 2],
        ['text' => 'File before the deadline', 'numId' => 2],
    ]));

    $lists = listBlocks($blocks);

    expect($lists)->toHaveCount(1)
        ->and($lists[0]['ordered'])->toBeTrue()
        ->and($lists[0]['items'])->toBe([
            'Gather your paperwork',
            'Work out your allowance',
            'File before the deadline',
        ]);
});

it('reads bullets and numbers from the same document independently', function () {
    $blocks = ingestPath(handBuiltDocx([
        ['text' => 'A bullet', 'numId' => 1],
        ['text' => 'Another bullet', 'numId' => 1],
        ['text' => 'Step one', 'numId' => 2],
        ['text' => 'Step two', 'numId' => 2],
    ]));

    $lists = listBlocks($blocks);

    expect($lists)->toHaveCount(2)
        ->and($lists[0]['ordered'])->toBeFalse()
        ->and($lists[0]['items'])->toHaveCount(2)
        ->and($lists[1]['ordered'])->toBeTrue()
        ->and($lists[1]['items'])->toHaveCount(2);
});

it('emits the ordered key as a boolean, which BlockValidator requires', function () {
    $blocks = ingestBlocks(function ($s) {
        $s->addListItem('Only item', 0, null, ListItemStyle::TYPE_BULLET_FILLED);
    });

    $list = listBlocks($blocks)[0];

    expect($list)->toHaveKeys(['type', 'ordered', 'items'])
        ->and($list['ordered'])->toBeBool()
        ->and($list)->not->toHaveKey('style');
});

it('validates as a well-formed list block', function () {
    $blocks = ingestBlocks(function ($s) {
        $s->addListItem('Something worth doing', 0, null, ListItemStyle::TYPE_NUMBER);
    });

    $errors = app(BlockValidator::class)->validate(listBlocks($blocks));

    expect($errors)->toBe([]);
});

it('keeps bold, italic and links inside a list item', function () {
    $blocks = ingestBlocks(function ($s) {
        $run = $s->addListItemRun(0, ListItemStyle::TYPE_BULLET_FILLED);
        $run->addText('Check the ');
        $run->addText('annual allowance', ['bold' => true]);
        $run->addText(' on ');
        $run->addLink('https://fynla.org/insights/allowances', 'our guide');
    });

    $items = listBlocks($blocks)[0]['items'];

    expect($items[0])->toContain('<strong>annual allowance</strong>')
        ->and($items[0])->toContain('<a href="/insights/allowances">our guide</a>');
});

it('splits two lists separated by a paragraph', function () {
    $blocks = ingestBlocks(function ($s) {
        $s->addListItem('First list, item one', 0, null, ListItemStyle::TYPE_BULLET_FILLED);
        $s->addListItem('First list, item two', 0, null, ListItemStyle::TYPE_BULLET_FILLED);
        $s->addText('A paragraph in between.');
        $s->addListItem('Second list, item one', 0, null, ListItemStyle::TYPE_BULLET_FILLED);
    });

    $lists = listBlocks($blocks);

    expect($lists)->toHaveCount(2)
        ->and($lists[0]['items'])->toHaveCount(2)
        ->and($lists[1]['items'])->toHaveCount(1);
});

it('does not merge a bulleted list into an adjacent numbered list', function () {
    $blocks = ingestPath(handBuiltDocx([
        ['text' => 'A bullet', 'numId' => 1],
        ['text' => 'A numbered step', 'numId' => 2],
    ]));

    $lists = listBlocks($blocks);

    expect($lists)->toHaveCount(2)
        ->and($lists[0]['ordered'])->toBeFalse()
        ->and($lists[1]['ordered'])->toBeTrue();
});

it('falls back to bullets when the document has no numbering definitions', function () {
    // A malformed or absent numbering.xml must not lose the list entirely.
    $blocks = ingestPath(handBuiltDocx([['text' => 'Orphan item', 'numId' => 99]]));

    $lists = listBlocks($blocks);

    expect($lists)->toHaveCount(1)
        ->and($lists[0]['ordered'])->toBeFalse()
        ->and($lists[0]['items'])->toBe(['Orphan item']);
});

it('leaves a document with no lists untouched', function () {
    $blocks = ingestBlocks(function ($s) {
        $s->addText('Just a paragraph.');
        $s->addText('And another.');
    });

    expect(listBlocks($blocks))->toBe([])
        ->and($blocks)->toHaveCount(2);
});

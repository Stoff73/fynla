<?php

declare(strict_types=1);

use App\Services\Documents\DocxMetadataExtractor;

it('extracts title, subject, description, creator, keywords from core.xml', function () {
    $extractor = new DocxMetadataExtractor();

    $meta = $extractor->extract(base_path('tests/fixtures/documents/sample-minimal.docx'));

    expect($meta)->toMatchArray([
        'title' => 'Minimal Sample Title',
        'subject' => 'A test subject',
        'description' => 'Description body',
        'creator' => 'Jane Doe',
        'keywords' => 'tax, savings, isa',
    ]);
});

it('returns nulls when core.xml is missing', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<x/>');
    $zip->close();

    $meta = (new DocxMetadataExtractor())->extract($tmp);

    expect($meta)->toBe([
        'title' => null,
        'subject' => null,
        'description' => null,
        'creator' => null,
        'keywords' => null,
    ]);

    unlink($tmp);
});

it('throws when the file is not a valid zip', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'notdocx');
    file_put_contents($tmp, 'not a zip');

    expect(fn () => (new DocxMetadataExtractor())->extract($tmp))
        ->toThrow(\RuntimeException::class, 'not a valid docx');

    unlink($tmp);
});

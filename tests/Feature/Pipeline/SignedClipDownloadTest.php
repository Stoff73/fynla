<?php

declare(strict_types=1);

use App\Http\Controllers\Pipeline\SignedClipDownloadController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function writeClipFixture(string $slug, string $filename): string
{
    $dir = storage_path("app/social/video/{$slug}");
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir.DIRECTORY_SEPARATOR.$filename;
    file_put_contents($path, 'fake mp4 bytes');

    return $path;
}

afterEach(function () {
    $dir = storage_path('app/social/video/clip-dl-test');
    if (is_dir($dir)) {
        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }
});

it('serves a first-pass clip over a signed URL', function () {
    writeClipFixture('clip-dl-test', 'clip-1.mp4');

    $url = URL::temporarySignedRoute('pipeline.clip.download', now()->addMinutes(30), [
        'slug' => 'clip-dl-test',
        'filename' => 'clip-1.mp4',
    ]);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');
});

// Regression: RegenerateClipJob writes clip-N-rM.mp4, but the route constraint and
// the controller regex only accepted clip-N.mp4. Every regenerated clip 404'd at
// routing — the approval email preview, the admin queue, and the video Buffer
// downloads for the scheduled post all broke at once.
it('serves a regenerated clip, whose filename carries the -rN suffix', function () {
    writeClipFixture('clip-dl-test', 'clip-2-r1.mp4');

    $url = URL::temporarySignedRoute('pipeline.clip.download', now()->addMinutes(30), [
        'slug' => 'clip-dl-test',
        'filename' => 'clip-2-r1.mp4',
    ]);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');
});

// The one contract the route, this controller, and the approvals queue all read.
// Asserted directly: an out-of-contract filename never reaches the route at all,
// so an HTTP assertion here would only be measuring the SPA catch-all.
it('accepts only clip filenames and nothing else', function (string $filename, bool $allowed) {
    expect((bool) preg_match('/^'.SignedClipDownloadController::FILENAME_PATTERN.'$/', $filename))
        ->toBe($allowed);
})->with([
    ['clip-1.mp4', true],
    ['clip-999.mp4', true],
    ['clip-2-r1.mp4', true],
    ['clip-2-r12.mp4', true],
    ['secrets.env', false],
    ['../../.env', false],
    ['clip-1.mp4.php', false],
    ['clip-.mp4', false],
]);

it('403s without a valid signature', function () {
    writeClipFixture('clip-dl-test', 'clip-1.mp4');

    $this->get('/pipeline/clips/clip-dl-test/clip-1.mp4')->assertForbidden();
});

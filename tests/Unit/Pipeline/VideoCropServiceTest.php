<?php

declare(strict_types=1);

use App\Services\Pipeline\VideoCropService;
use Tests\TestCase;

uses(TestCase::class);

it('uses the portable MPEG-4 encoder profile when configured', function () {
    config()->set('pipeline.video.encoder', 'mpeg4');

    $directory = sys_get_temp_dir().'/fynla-ffmpeg-'.bin2hex(random_bytes(6));
    mkdir($directory, 0755, true);
    $fakeFfmpeg = $directory.'/ffmpeg';
    $argumentsPath = $directory.'/arguments.txt';
    $sourcePath = $directory.'/source.mp4';
    $outputPath = $directory.'/output.mp4';
    file_put_contents($sourcePath, 'source');
    file_put_contents($fakeFfmpeg, '#!/bin/sh'."\n".sprintf(<<<'SH'
printf '%%s\n' "$@" > %s
for last_argument do :; done
printf 'rendered' > "$last_argument"
SH, escapeshellarg($argumentsPath)));
    chmod($fakeFfmpeg, 0755);

    $originalPath = getenv('PATH') ?: '';
    putenv('PATH='.$directory.':'.$originalPath);

    try {
        (new VideoCropService)->cropAndBurn($sourcePath, $outputPath, 0, 5);
        $arguments = file($argumentsPath, FILE_IGNORE_NEW_LINES);

        expect($arguments)->toContain('-c:v')
            ->toContain('mpeg4')
            ->toContain('-q:v')
            ->toContain('3')
            ->not->toContain('libx264')
            ->not->toContain('-preset')
            ->not->toContain('-crf');
    } finally {
        putenv('PATH='.$originalPath);
        @unlink($sourcePath);
        @unlink($outputPath);
        @unlink($argumentsPath);
        @unlink($fakeFfmpeg);
        @rmdir($directory);
    }
});

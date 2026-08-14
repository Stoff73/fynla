<?php

declare(strict_types=1);

use App\Services\Pipeline\LocalWhisperTranscriber;
use Tests\TestCase;

uses(TestCase::class);

it('uses the configured Whisper executable', function () {
    config()->set('pipeline.whisper.binary', '/usr/bin/true');

    $videoPath = tempnam(sys_get_temp_dir(), 'fynla-whisper-').'.mp4';
    file_put_contents($videoPath, 'test video placeholder');

    try {
        expect(fn () => (new LocalWhisperTranscriber)->transcribe($videoPath))
            ->toThrow(RuntimeException::class, 'Whisper did not write expected transcript');
    } finally {
        @unlink($videoPath);
    }
});

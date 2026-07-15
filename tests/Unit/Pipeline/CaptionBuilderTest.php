<?php

declare(strict_types=1);

use App\Services\Pipeline\CaptionBuilder;

beforeEach(function () {
    $this->tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pipeline-caption-test-'.uniqid().'.srt';
});

afterEach(function () {
    if (file_exists($this->tempPath)) {
        @unlink($this->tempPath);
    }
});

it('builds an SRT covering the full transcript when no clip range is set', function () {
    $transcript = [
        'segments' => [
            ['start' => 0.0,  'end' => 3.5,  'text' => 'Hello there.'],
            ['start' => 3.5,  'end' => 7.2,  'text' => 'Welcome to the show.'],
        ],
    ];

    (new CaptionBuilder())->buildSrt($transcript, $this->tempPath);

    $srt = file_get_contents($this->tempPath);

    expect($srt)->toContain('1')
        ->and($srt)->toContain('00:00:00,000 --> 00:00:03,500')
        ->and($srt)->toContain('Hello there.')
        ->and($srt)->toContain('00:00:03,500 --> 00:00:07,200')
        ->and($srt)->toContain('Welcome to the show.');
});

it('shifts timecodes to be relative to the clip start when a range is given', function () {
    $transcript = [
        'segments' => [
            ['start' => 30.0, 'end' => 33.5, 'text' => 'Segment one.'],
            ['start' => 34.0, 'end' => 38.0, 'text' => 'Segment two.'],
            ['start' => 45.0, 'end' => 50.0, 'text' => 'Outside the clip.'],
        ],
    ];

    (new CaptionBuilder())->buildSrt($transcript, $this->tempPath, clipStart: 30.0, clipEnd: 40.0);

    $srt = file_get_contents($this->tempPath);

    expect($srt)->toContain('00:00:00,000 --> 00:00:03,500')
        ->and($srt)->toContain('Segment one.')
        ->and($srt)->toContain('00:00:04,000 --> 00:00:08,000')
        ->and($srt)->toContain('Segment two.')
        ->and($srt)->not->toContain('Outside the clip.');
});

it('skips empty segments and very short segments', function () {
    $transcript = [
        'segments' => [
            ['start' => 0.0, 'end' => 3.0, 'text' => 'Real content.'],
            ['start' => 3.0, 'end' => 3.05, 'text' => 'x'],
            ['start' => 4.0, 'end' => 5.0, 'text' => ''],
        ],
    ];

    (new CaptionBuilder())->buildSrt($transcript, $this->tempPath);

    $srt = file_get_contents($this->tempPath);

    expect($srt)->toContain('Real content.')
        ->and(substr_count($srt, ' --> '))->toBe(1);
});

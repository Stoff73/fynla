<?php

declare(strict_types=1);

use App\Jobs\Pipeline\ProcessVideoJob;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\PipelineArticle;
use App\Services\Pipeline\Google\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

it('retries a failed video when the same Drive file is still present', function () {
    Config::set('pipeline.enabled', true);
    Config::set('pipeline.google.drive_folder_id', 'ROOT_FOLDER');
    Cache::put('pipeline.google.drive.videos_folder_id', 'VIDEOS_FOLDER');
    Bus::fake();

    $source = InsightArticle::factory()->published()->create([
        'slug' => 'controlled-video-test',
    ]);
    $pipeline = PipelineArticle::create([
        'insight_article_id' => $source->id,
        'status' => 'failed',
        'source_video_drive_file_id' => 'VIDEO_FILE_1',
        'last_error' => 'Previous render failed.',
    ]);

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('listFiles')
        ->once()
        ->with('VIDEOS_FOLDER')
        ->andReturn([[
            'id' => 'VIDEO_FILE_1',
            'name' => 'controlled-video-test.mp4',
            'mimeType' => 'video/mp4',
            'webViewLink' => 'https://drive.google.com/file/d/VIDEO_FILE_1/view',
        ]]);
    app()->instance(GoogleDriveService::class, $drive);

    $this->artisan('pipeline:detect-new-videos')
        ->expectsOutputToContain('queue')
        ->assertExitCode(0);

    expect($pipeline->fresh()->status)->toBe('rendering')
        ->and($pipeline->fresh()->last_error)->toBeNull();
    Bus::assertDispatched(ProcessVideoJob::class);
});

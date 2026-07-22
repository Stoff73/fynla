<?php

declare(strict_types=1);

use App\Jobs\Pipeline\SyncDriveChangesJob;
use App\Services\Pipeline\Google\ArticlesFolderLocator;
use App\Services\Pipeline\Google\DriveChangeRouter;
use App\Services\Pipeline\Google\VideosFolderLocator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.drive.webhook_token', 'secret-token');
});

it('rejects a ping with a missing or wrong channel token', function () {
    Bus::fake();

    $this->withHeaders(['X-Goog-Channel-Token' => 'WRONG', 'X-Goog-Resource-State' => 'change'])
        ->post('/pipeline/drive/webhook')
        ->assertStatus(403);

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('acks the initial sync handshake without dispatching work', function () {
    Bus::fake();

    $this->withHeaders(['X-Goog-Channel-Token' => 'secret-token', 'X-Goog-Resource-State' => 'sync'])
        ->post('/pipeline/drive/webhook')
        ->assertOk();

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('dispatches the sync job on a real change ping with a valid token', function () {
    Bus::fake();

    $this->withHeaders(['X-Goog-Channel-Token' => 'secret-token', 'X-Goog-Resource-State' => 'change'])
        ->post('/pipeline/drive/webhook')
        ->assertOk();

    Bus::assertDispatched(SyncDriveChangesJob::class);
});

it('routes a new .docx in Articles and a new .mp4 in Videos to the right detectors', function () {
    $articles = Mockery::mock(ArticlesFolderLocator::class);
    $articles->shouldReceive('resolve')->andReturn('ARTICLES_FOLDER');
    $videos = Mockery::mock(VideosFolderLocator::class);
    $videos->shouldReceive('resolve')->andReturn('VIDEOS_FOLDER');

    $router = new DriveChangeRouter($articles, $videos);

    $result = $router->classify([
        ['file' => ['name' => 'my-post.docx', 'mimeType' => 'application/vnd...', 'parents' => ['ARTICLES_FOLDER'], 'trashed' => false]],
        ['file' => ['name' => 'my-post.mp4', 'mimeType' => 'video/mp4', 'parents' => ['VIDEOS_FOLDER'], 'trashed' => false]],
        ['file' => ['name' => 'random.txt', 'mimeType' => 'text/plain', 'parents' => ['SOMEWHERE_ELSE'], 'trashed' => false]],
    ]);

    expect($result)->toBe(['articles' => true, 'videos' => true]);
});

it('ignores trashed files and unrelated folders', function () {
    $articles = Mockery::mock(ArticlesFolderLocator::class);
    $articles->shouldReceive('resolve')->andReturn('ARTICLES_FOLDER');
    $videos = Mockery::mock(VideosFolderLocator::class);
    $videos->shouldReceive('resolve')->andReturn('VIDEOS_FOLDER');

    $router = new DriveChangeRouter($articles, $videos);

    $result = $router->classify([
        ['file' => ['name' => 'old.docx', 'parents' => ['ARTICLES_FOLDER'], 'trashed' => true]],
        ['file' => ['name' => 'clip.mp4', 'parents' => ['SOME_OTHER_FOLDER'], 'trashed' => false]],
    ]);

    expect($result)->toBe(['articles' => false, 'videos' => false]);
});

afterEach(fn () => Mockery::close());

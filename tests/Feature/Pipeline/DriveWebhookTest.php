<?php

declare(strict_types=1);

use App\Jobs\Pipeline\SyncDriveChangesJob;
use App\Models\Pipeline\DriveWatchChannel;
use App\Services\Pipeline\Google\ArticlesFolderLocator;
use App\Services\Pipeline\Google\DriveChangeRouter;
use App\Services\Pipeline\Google\VideosFolderLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.drive.webhook_token', 'secret-token');
    Cache::forget('pipeline:drive-changes:pending');
});

function activeDriveWatchChannel(array $attributes = []): DriveWatchChannel
{
    return DriveWatchChannel::create(array_merge([
        'channel_id' => 'active-channel',
        'resource_id' => 'active-resource',
        'page_token' => 'page-token',
        'expires_at' => now()->addHour(),
    ], $attributes));
}

function validDriveWebhookHeaders(array $headers = []): array
{
    return array_merge([
        'X-Goog-Channel-Token' => 'secret-token',
        'X-Goog-Channel-ID' => 'active-channel',
        'X-Goog-Resource-ID' => 'active-resource',
        'X-Goog-Resource-State' => 'change',
    ], $headers);
}

it('rejects a ping with a missing channel token', function () {
    Bus::fake();
    activeDriveWatchChannel();

    $this->withHeaders([
        'X-Goog-Channel-ID' => 'active-channel',
        'X-Goog-Resource-ID' => 'active-resource',
        'X-Goog-Resource-State' => 'change',
    ])
        ->post('/pipeline/drive/webhook')
        ->assertStatus(403);

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('rejects a ping with a wrong channel token', function () {
    Bus::fake();
    activeDriveWatchChannel();

    $this->withHeaders(validDriveWebhookHeaders(['X-Goog-Channel-Token' => 'WRONG']))
        ->post('/pipeline/drive/webhook')
        ->assertStatus(403);

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('rejects a ping from an unknown channel', function () {
    Bus::fake();
    activeDriveWatchChannel();

    $this->withHeaders(validDriveWebhookHeaders(['X-Goog-Channel-ID' => 'unknown-channel']))
        ->post('/pipeline/drive/webhook')
        ->assertStatus(403);

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('rejects a ping with the wrong resource', function () {
    Bus::fake();
    activeDriveWatchChannel();

    $this->withHeaders(validDriveWebhookHeaders(['X-Goog-Resource-ID' => 'wrong-resource']))
        ->post('/pipeline/drive/webhook')
        ->assertStatus(403);

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('rejects a ping for an expired channel', function () {
    Bus::fake();
    activeDriveWatchChannel(['expires_at' => now()->subSecond()]);

    $this->withHeaders(validDriveWebhookHeaders())
        ->post('/pipeline/drive/webhook')
        ->assertStatus(403);

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('rejects a ping for a stopped channel', function () {
    Bus::fake();
    $channel = activeDriveWatchChannel();
    $channel->delete();

    $this->withHeaders(validDriveWebhookHeaders())
        ->post('/pipeline/drive/webhook')
        ->assertStatus(403);

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('acks the initial sync handshake without dispatching work', function () {
    Bus::fake();
    activeDriveWatchChannel();

    $this->withHeaders(validDriveWebhookHeaders(['X-Goog-Resource-State' => 'sync']))
        ->post('/pipeline/drive/webhook')
        ->assertOk();

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
});

it('dispatches the sync job on a real change ping with a valid token', function () {
    Bus::fake();
    activeDriveWatchChannel();

    $this->withHeaders(validDriveWebhookHeaders())
        ->post('/pipeline/drive/webhook')
        ->assertOk();

    Bus::assertDispatched(SyncDriveChangesJob::class);
});

it('coalesces repeated valid notifications while change sync work is pending', function () {
    Bus::fake();
    activeDriveWatchChannel();

    $this->withHeaders(validDriveWebhookHeaders())
        ->post('/pipeline/drive/webhook')
        ->assertOk();

    $this->withHeaders(validDriveWebhookHeaders())
        ->post('/pipeline/drive/webhook')
        ->assertOk();

    Bus::assertDispatchedTimes(SyncDriveChangesJob::class, 1);
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

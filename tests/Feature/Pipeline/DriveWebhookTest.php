<?php

declare(strict_types=1);

use App\Jobs\Pipeline\SyncDriveChangesJob;
use App\Models\Pipeline\DriveWatchChannel;
use App\Services\Pipeline\Google\ArticlesFolderLocator;
use App\Services\Pipeline\Google\DriveChangeRouter;
use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\VideosFolderLocator;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.drive.webhook_token', 'secret-token');
    Cache::lock(SyncDriveChangesJob::PENDING_CLAIM_CACHE_KEY)->forceRelease();
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

function pendingDriveSyncClaimIsAvailable(): bool
{
    $lock = Cache::lock(SyncDriveChangesJob::PENDING_CLAIM_CACHE_KEY, 180, 'test-owner');
    $acquired = $lock->get();

    if ($acquired) {
        $lock->release();
    }

    return $acquired;
}

function pendingDriveSyncClaimIsOwnedBy(string $owner): bool
{
    return Cache::restoreLock(SyncDriveChangesJob::PENDING_CLAIM_CACHE_KEY, $owner)
        ->isOwnedByCurrentProcess();
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

it('rejects missing or unknown resource states without dispatching work', function (array $headers) {
    Bus::fake();
    activeDriveWatchChannel();

    $this->withHeaders($headers)
        ->post('/pipeline/drive/webhook')
        ->assertStatus(400);

    Bus::assertNotDispatched(SyncDriveChangesJob::class);
})->with([
    'missing state' => [[
        'X-Goog-Channel-Token' => 'secret-token',
        'X-Goog-Channel-ID' => 'active-channel',
        'X-Goog-Resource-ID' => 'active-resource',
    ]],
    'unknown state' => [validDriveWebhookHeaders(['X-Goog-Resource-State' => 'unknown'])],
]);

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

it('releases its pending claim when dispatch fails so Google can retry', function () {
    activeDriveWatchChannel();

    $dispatcher = Mockery::mock(QueueingDispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->andThrow(new RuntimeException('Queue is unavailable.'));
    app()->instance(Dispatcher::class, $dispatcher);

    $this->withHeaders(validDriveWebhookHeaders())
        ->post('/pipeline/drive/webhook')
        ->assertStatus(503);

    expect(pendingDriveSyncClaimIsAvailable())->toBeTrue();

    Bus::fake();

    $this->withHeaders(validDriveWebhookHeaders())
        ->post('/pipeline/drive/webhook')
        ->assertOk();

    Bus::assertDispatched(SyncDriveChangesJob::class);
});

it('releases itself for retry and retains its pending claim when the stream lock is busy', function () {
    activeDriveWatchChannel();
    Cache::lock(SyncDriveChangesJob::PENDING_CLAIM_CACHE_KEY, 180, 'claim-owner')->get();
    $streamLock = Cache::lock('pipeline:drive-changes', 120);
    $streamLock->get();

    $queueJob = Mockery::mock(QueueJob::class);
    $queueJob->shouldReceive('release')->once()->with(5);

    (new SyncDriveChangesJob('claim-owner'))
        ->setJob($queueJob)
        ->handle(
            Mockery::mock(GoogleDriveService::class),
            Mockery::mock(DriveChangeRouter::class),
        );

    expect(pendingDriveSyncClaimIsOwnedBy('claim-owner'))->toBeTrue();

    $streamLock->release();
});

it('clears its owned pending claim after acquiring the stream lock so later changes queue work', function () {
    Bus::fake();
    activeDriveWatchChannel();
    Cache::lock(SyncDriveChangesJob::PENDING_CLAIM_CACHE_KEY, 180, 'claim-owner')->get();

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('listChanges')->once()->with('page-token')->andReturn([
        'changes' => [],
        'newStartPageToken' => 'next-page-token',
    ]);
    $router = Mockery::mock(DriveChangeRouter::class);
    $router->shouldReceive('classify')->once()->with([])->andReturn([
        'articles' => false,
        'videos' => false,
    ]);

    (new SyncDriveChangesJob('claim-owner'))->handle($drive, $router);

    expect(pendingDriveSyncClaimIsAvailable())->toBeTrue();

    $this->withHeaders(validDriveWebhookHeaders())
        ->post('/pipeline/drive/webhook')
        ->assertOk();

    Bus::assertDispatched(SyncDriveChangesJob::class);
});

it('keeps the change stream serialized beyond the job timeout boundary', function () {
    activeDriveWatchChannel();

    $secondWorkerEntered = null;
    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('listChanges')->once()->with('page-token')->andReturnUsing(function () use (&$secondWorkerEntered): array {
        $this->travel(181)->seconds();

        $contender = Cache::lock('pipeline:drive-changes', 120, 'second-worker');
        $secondWorkerEntered = $contender->get();
        if ($secondWorkerEntered) {
            $contender->release();
        }

        return [
            'changes' => [],
            'newStartPageToken' => 'next-page-token',
        ];
    });
    $router = Mockery::mock(DriveChangeRouter::class);
    $router->shouldReceive('classify')->once()->with([])->andReturn([
        'articles' => false,
        'videos' => false,
    ]);

    (new SyncDriveChangesJob('claim-owner'))->handle($drive, $router);

    expect($secondWorkerEntered)->toBeFalse();
});

it('does not clear another pending claim when it fails permanently', function () {
    Cache::lock(SyncDriveChangesJob::PENDING_CLAIM_CACHE_KEY, 180, 'another-owner')->get();

    (new SyncDriveChangesJob('claim-owner'))->failed(new RuntimeException('Permanent failure.'));

    expect(pendingDriveSyncClaimIsOwnedBy('another-owner'))->toBeTrue();
});

it('retains its pending owner through 120 seconds of stream-lock contention before handing off', function () {
    Bus::fake();
    activeDriveWatchChannel();
    Cache::lock(SyncDriveChangesJob::PENDING_CLAIM_CACHE_KEY, 180, 'claim-owner')->get();
    $streamLock = Cache::lock('pipeline:drive-changes', 120);
    $streamLock->get();

    $queueJob = Mockery::mock(QueueJob::class);
    $queueJob->shouldReceive('release')->times(24)->with(5);
    $job = (new SyncDriveChangesJob('claim-owner'))->setJob($queueJob);

    for ($attempt = 0; $attempt < 24; $attempt++) {
        $job->handle(
            Mockery::mock(GoogleDriveService::class),
            Mockery::mock(DriveChangeRouter::class),
        );

        expect(pendingDriveSyncClaimIsOwnedBy('claim-owner'))->toBeTrue();
    }

    $streamLock->release();

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('listChanges')->once()->with('page-token')->andReturn([
        'changes' => [],
        'newStartPageToken' => 'next-page-token',
    ]);
    $router = Mockery::mock(DriveChangeRouter::class);
    $router->shouldReceive('classify')->once()->with([])->andReturn([
        'articles' => false,
        'videos' => false,
    ]);

    $job->handle($drive, $router);

    expect(pendingDriveSyncClaimIsAvailable())->toBeTrue();

    $this->withHeaders(validDriveWebhookHeaders())
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

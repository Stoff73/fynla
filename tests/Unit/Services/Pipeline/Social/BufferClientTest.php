<?php

declare(strict_types=1);

use App\Services\Pipeline\Social\BufferClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('pipeline.buffer.access_token', 'fake-token');
    Config::set('pipeline.buffer.profiles.instagram', 'ig-channel-id-abc');
    Config::set('pipeline.buffer.profiles.facebook', 'fb-channel-id-def');
    Config::set('pipeline.buffer.profiles.tiktok', '');
    Config::set('pipeline.buffer.timeout_seconds', 15);
});

it('POSTs the createPost mutation to api.buffer.com/graphql on schedule', function () {
    Http::fake([
        'api.buffer.com/graphql' => Http::response([
            'data' => ['createPost' => [
                '__typename' => 'PostActionSuccess',
                'post' => ['id' => 'buffer-post-123', 'status' => 'sent', 'dueAt' => '2026-07-22T12:00:00Z', 'channelId' => 'ig-channel-id-abc'],
            ]],
        ]),
    ]);

    $client = new BufferClient;
    $result = $client->schedule(
        'instagram',
        'Caption text',
        'https://fynla.org/pipeline/clips/example/clip-1.mp4?signature=abc',
        new DateTimeImmutable('2026-07-22T12:00:00Z'),
    );

    expect($result['updates'][0]['id'])->toBe('buffer-post-123');

    Http::assertSent(function ($request) {
        $payload = $request->data();
        return $request->url() === 'https://api.buffer.com/graphql'
            && $request->hasHeader('Authorization', 'Bearer fake-token')
            && str_contains($payload['query'], 'mutation CreatePost')
            && $payload['variables']['input']['channelId'] === 'ig-channel-id-abc'
            && $payload['variables']['input']['mode'] === 'customScheduled'
            && $payload['variables']['input']['assets'][0]['video']['url'] === 'https://fynla.org/pipeline/clips/example/clip-1.mp4?signature=abc'
            && $payload['variables']['input']['metadata']['instagram']['type'] === 'reel'
            && $payload['variables']['input']['metadata']['instagram']['shouldShareToFeed'] === true;
    });
});

it('sends shareNow mode when no dueAt is supplied', function () {
    Http::fake([
        'api.buffer.com/graphql' => Http::response([
            'data' => ['createPost' => [
                '__typename' => 'PostActionSuccess',
                'post' => ['id' => 'x', 'status' => 'sent', 'dueAt' => null, 'channelId' => 'ig-channel-id-abc'],
            ]],
        ]),
    ]);

    (new BufferClient)->schedule('instagram', 'now', 'https://cdn.fynla.org/clip.mp4');

    Http::assertSent(function ($request) {
        $input = $request->data()['variables']['input'];
        return $input['mode'] === 'shareNow' && ! array_key_exists('dueAt', $input);
    });
});

it('throws when createPost returns an error variant', function () {
    Http::fake([
        'api.buffer.com/graphql' => Http::response([
            'data' => ['createPost' => [
                '__typename' => 'UnauthorizedError',
                'message' => 'Invalid channel authorisation',
            ]],
        ]),
    ]);

    (new BufferClient)->schedule('instagram', 'text', 'https://cdn.fynla.org/clip.mp4', new DateTimeImmutable('2026-07-22T12:00:00Z'));
})->throws(RuntimeException::class, 'Buffer schedule failed (UnauthorizedError)');

it('throws when the platform has no configured channel ID', function () {
    (new BufferClient)->schedule('tiktok', 'text', 'https://cdn.fynla.org/clip.mp4');
})->throws(RuntimeException::class, 'BUFFER_PROFILE_TIKTOK');

it('throws when GraphQL returns top-level errors', function () {
    Http::fake([
        'api.buffer.com/graphql' => Http::response([
            'errors' => [['message' => 'Field not found']],
        ]),
    ]);

    (new BufferClient)->schedule('instagram', 'text', 'https://cdn.fynla.org/clip.mp4', new DateTimeImmutable('2026-07-22T12:00:00Z'));
})->throws(RuntimeException::class, 'Buffer GraphQL failed (schedule): Field not found');

it('throws on non-2xx HTTP responses with the status in the message', function () {
    Http::fake([
        'api.buffer.com/graphql' => Http::response('server melted', 502),
    ]);

    (new BufferClient)->schedule('instagram', 'text', 'https://cdn.fynla.org/clip.mp4', new DateTimeImmutable('2026-07-22T12:00:00Z'));
})->throws(RuntimeException::class, 'HTTP 502');

it('walks account → channels for listProfiles()', function () {
    Http::fakeSequence('api.buffer.com/graphql')
        ->push([
            'data' => ['account' => ['organizations' => [
                ['id' => 'org-1', 'name' => 'Fynla'],
            ]]],
        ])
        ->push([
            'data' => ['channels' => [
                ['id' => 'ig-1', 'service' => 'instagram', 'serviceId' => 'IG123', 'name' => '@fynla', 'isDisconnected' => false],
                ['id' => 'fb-1', 'service' => 'facebook', 'serviceId' => 'FB456', 'name' => 'Fynla Page', 'isDisconnected' => false],
            ]],
        ]);

    $profiles = (new BufferClient)->listProfiles();

    expect($profiles)->toHaveCount(2)
        ->and($profiles[0]['service'])->toBe('instagram')
        ->and($profiles[1]['service'])->toBe('facebook');
});

it('returns an empty metrics array when Buffer returns errors for a post fetch', function () {
    Http::fake([
        'api.buffer.com/graphql' => Http::response([
            'errors' => [['message' => 'Post not found']],
        ]),
    ]);

    expect((new BufferClient)->fetchUpdateMetrics('missing-id'))->toBe([]);
});

it('uses facebook metadata block for facebook posts', function () {
    Http::fake([
        'api.buffer.com/graphql' => Http::response([
            'data' => ['createPost' => [
                '__typename' => 'PostActionSuccess',
                'post' => ['id' => 'x', 'status' => 'sent', 'dueAt' => null, 'channelId' => 'fb-channel-id-def'],
            ]],
        ]),
    ]);

    (new BufferClient)->schedule('facebook', 'body', 'https://cdn.fynla.org/clip.mp4', new DateTimeImmutable('2026-07-22T12:00:00Z'));

    Http::assertSent(function ($request) {
        $input = $request->data()['variables']['input'];
        return $input['channelId'] === 'fb-channel-id-def'
            && $input['metadata']['facebook']['type'] === 'reel';
    });
});

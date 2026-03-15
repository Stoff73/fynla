<?php

declare(strict_types=1);

use App\Http\Middleware\ETagResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

describe('ETagResponse Middleware', function () {
    beforeEach(function () {
        $this->middleware = new ETagResponse;
    });

    it('adds ETag header to GET 200 responses', function () {
        $request = Request::create('/test', 'GET');
        $response = new Response('Hello World', 200);

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->headers->has('ETag'))->toBeTrue()
            ->and($result->headers->get('ETag'))->toBe('"'.md5('Hello World').'"');
    });

    it('returns 304 when If-None-Match matches ETag', function () {
        $content = 'Hello World';
        $etag = '"'.md5($content).'"';

        $request = Request::create('/test', 'GET');
        $request->headers->set('If-None-Match', $etag);

        $response = new Response($content, 200);

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->getStatusCode())->toBe(304)
            ->and($result->getContent())->toBe('');
    });

    it('returns 200 when If-None-Match does not match', function () {
        $request = Request::create('/test', 'GET');
        $request->headers->set('If-None-Match', '"different-etag"');

        $response = new Response('Hello World', 200);

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->getStatusCode())->toBe(200)
            ->and($result->headers->get('ETag'))->toBe('"'.md5('Hello World').'"');
    });

    it('does not add ETag to POST requests', function () {
        $request = Request::create('/test', 'POST');
        $response = new Response('Created', 201);

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->headers->has('ETag'))->toBeFalse();
    });

    it('does not add ETag to non-200 responses', function () {
        $request = Request::create('/test', 'GET');
        $response = new Response('Not Found', 404);

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->headers->has('ETag'))->toBeFalse();
    });

    it('does not add ETag to empty responses', function () {
        $request = Request::create('/test', 'GET');
        $response = new Response('', 200);

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->headers->has('ETag'))->toBeFalse();
    });
});

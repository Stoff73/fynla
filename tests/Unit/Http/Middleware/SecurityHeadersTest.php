<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

describe('SecurityHeaders middleware', function () {
    beforeEach(function () {
        $this->middleware = new SecurityHeaders;
    });

    it('sets X-Frame-Options to DENY (canonical, not SAMEORIGIN)', function () {
        $response = $this->middleware->handle(
            Request::create('/test', 'GET'),
            fn () => new Response('', 200)
        );

        expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
    });

    it('does NOT set the deprecated X-XSS-Protection header', function () {
        $response = $this->middleware->handle(
            Request::create('/test', 'GET'),
            fn () => new Response('', 200)
        );

        expect($response->headers->has('X-XSS-Protection'))->toBeFalse();
    });

    it('sets X-Content-Type-Options to nosniff', function () {
        $response = $this->middleware->handle(
            Request::create('/test', 'GET'),
            fn () => new Response('', 200)
        );

        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    });

    it('sets Referrer-Policy', function () {
        $response = $this->middleware->handle(
            Request::create('/test', 'GET'),
            fn () => new Response('', 200)
        );

        expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    });

    it('sets Content-Security-Policy', function () {
        $response = $this->middleware->handle(
            Request::create('/test', 'GET'),
            fn () => new Response('', 200)
        );

        expect($response->headers->has('Content-Security-Policy'))->toBeTrue();
    });

    it('sets Permissions-Policy', function () {
        $response = $this->middleware->handle(
            Request::create('/test', 'GET'),
            fn () => new Response('', 200)
        );

        expect($response->headers->has('Permissions-Policy'))->toBeTrue();
    });

    it('sets Cross-Origin-Opener-Policy', function () {
        $response = $this->middleware->handle(
            Request::create('/test', 'GET'),
            fn () => new Response('', 200)
        );

        expect($response->headers->get('Cross-Origin-Opener-Policy'))->toBe('same-origin');
    });
});

describe('SecurityHeaders .htaccess regression', function () {
    it('public/.htaccess does not set X-Frame-Options (middleware does)', function () {
        $contents = file_get_contents(base_path('public/.htaccess'));

        expect($contents)->not->toMatch('/Header\s+set\s+X-Frame-Options/i');
    });

    it('public/.htaccess does not set X-XSS-Protection (deprecated)', function () {
        $contents = file_get_contents(base_path('public/.htaccess'));

        expect($contents)->not->toMatch('/Header\s+set\s+X-XSS-Protection/i');
    });

    it('deploy templates do not set X-Frame-Options', function () {
        $csjones = file_get_contents(base_path('deploy/csjones-fynla/.htaccess'));
        $prod = file_get_contents(base_path('deploy/fynla-org/.htaccess'));

        expect($csjones)->not->toMatch('/Header\s+set\s+X-Frame-Options/i')
            ->and($prod)->not->toMatch('/Header\s+set\s+X-Frame-Options/i');
    });

    it('deploy templates do not set X-XSS-Protection', function () {
        $csjones = file_get_contents(base_path('deploy/csjones-fynla/.htaccess'));
        $prod = file_get_contents(base_path('deploy/fynla-org/.htaccess'));

        expect($csjones)->not->toMatch('/Header\s+set\s+X-XSS-Protection/i')
            ->and($prod)->not->toMatch('/Header\s+set\s+X-XSS-Protection/i');
    });
});

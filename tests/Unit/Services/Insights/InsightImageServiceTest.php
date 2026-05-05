<?php

declare(strict_types=1);

use App\Services\Insights\InsightImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->service = app(InsightImageService::class);
});

it('uploads an image and returns three paths', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1200);

    $result = $this->service->upload($file, 'my-article');

    expect($result)->toHaveKeys(['path', 'card_path', 'thumb_path']);
    Storage::disk('public')->assertExists($result['path']);
    Storage::disk('public')->assertExists($result['card_path']);
    Storage::disk('public')->assertExists($result['thumb_path']);
});

it('resizes the card image to 800x450', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1200);

    $result = $this->service->upload($file, 'my-article');
    $image = getimagesize(Storage::disk('public')->path($result['card_path']));

    expect($image[0])->toBe(800)->and($image[1])->toBe(450);
});

it('resizes the thumbnail to 200x200', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1200);

    $result = $this->service->upload($file, 'my-article');
    $image = getimagesize(Storage::disk('public')->path($result['thumb_path']));

    expect($image[0])->toBe(200)->and($image[1])->toBe(200);
});

it('rejects unsupported formats', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $this->service->upload($file, 'my-article');
})->throws(InvalidArgumentException::class);

it('stores files under the slug directory', function () {
    $file = UploadedFile::fake()->image('photo.jpg');

    $result = $this->service->upload($file, 'my-article');

    expect($result['path'])->toStartWith('insights/my-article/');
});

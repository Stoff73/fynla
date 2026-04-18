<?php

declare(strict_types=1);

namespace App\Services\Insights;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

class InsightImageService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_BYTES = 10 * 1024 * 1024;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function upload(UploadedFile $file, string $slug): array
    {
        $this->validate($file);

        $directory = "insights/{$slug}";
        $timestamp = now()->format('YmdHis');
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());

        $originalPath = "{$directory}/{$timestamp}-{$basename}.{$extension}";
        Storage::disk('public')->put($originalPath, file_get_contents($file->getRealPath()));

        $cardPath = "{$directory}/{$timestamp}-{$basename}-card.webp";
        $thumbPath = "{$directory}/{$timestamp}-{$basename}-thumb.webp";

        $card = $this->manager->decodePath($file->getRealPath())
            ->cover(800, 450)
            ->encode(new WebpEncoder(quality: 85));
        Storage::disk('public')->put($cardPath, (string) $card);

        $thumb = $this->manager->decodePath($file->getRealPath())
            ->cover(200, 200)
            ->encode(new WebpEncoder(quality: 80));
        Storage::disk('public')->put($thumbPath, (string) $thumb);

        return [
            'path' => $originalPath,
            'card_path' => $cardPath,
            'thumb_path' => $thumbPath,
        ];
    }

    private function validate(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException("Unsupported image format: {$file->getMimeType()}");
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('Image exceeds 10 MB limit.');
        }
    }
}

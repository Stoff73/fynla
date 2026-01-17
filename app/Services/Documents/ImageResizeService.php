<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service to resize images to meet Claude API size limits.
 *
 * Claude API limits:
 * - Maximum 5MB per image
 * - Recommended: Resize to no more than 1.15 megapixels (1568 px max dimension)
 * - Supported: JPEG, PNG, GIF, WebP
 */
class ImageResizeService
{
    // Claude API has 5MB limit for images
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;

    // Recommended max dimension for optimal performance
    private const MAX_DIMENSION = 1568;

    // Target size with some buffer (4.5MB)
    private const TARGET_SIZE = 4.5 * 1024 * 1024;

    // Minimum JPEG quality to try
    private const MIN_QUALITY = 40;

    /**
     * Resize and compress an image if it exceeds Claude's API limits.
     * Returns base64-encoded image data.
     *
     * @param  string  $base64Data  Original base64-encoded image
     * @param  string  $mediaType  MIME type of the image
     * @return array{data: string, media_type: string, was_resized: bool}
     */
    public function processForClaudeAPI(string $base64Data, string $mediaType): array
    {
        $originalSize = strlen($base64Data) * 3 / 4; // Approximate original binary size

        // If under limit, no processing needed
        if ($originalSize <= self::MAX_IMAGE_SIZE) {
            return [
                'data' => $base64Data,
                'media_type' => $mediaType,
                'was_resized' => false,
            ];
        }

        Log::info('Image exceeds Claude API limit, resizing', [
            'original_size_mb' => round($originalSize / 1024 / 1024, 2),
            'media_type' => $mediaType,
        ]);

        // Increase memory limit for large image processing
        $oldMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            // Decode the base64 image
            $imageData = base64_decode($base64Data);
            if ($imageData === false) {
                throw new RuntimeException('Failed to decode base64 image data');
            }

            // Create image resource from data
            $image = @imagecreatefromstring($imageData);
            if ($image === false) {
                throw new RuntimeException('Failed to create image from data - GD cannot process this image');
            }

            // Get original dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Calculate new dimensions to fit within MAX_DIMENSION
            [$newWidth, $newHeight] = $this->calculateNewDimensions($width, $height);

            // Resize if dimensions changed
            if ($newWidth !== $width || $newHeight !== $height) {
                $resized = $this->resizeImage($image, $width, $height, $newWidth, $newHeight);
                imagedestroy($image);
                $image = $resized;
            }

            // Compress and encode as JPEG (best compression ratio)
            $result = $this->compressToJpeg($image);

            imagedestroy($image);

            return [
                'data' => $result,
                'media_type' => 'image/jpeg',
                'was_resized' => true,
            ];

        } finally {
            // Restore original memory limit
            ini_set('memory_limit', $oldMemoryLimit);
        }
    }

    /**
     * Resize image using imagecreatetruecolor and imagecopyresampled.
     * This is more reliable than imagescale for large images.
     */
    private function resizeImage(\GdImage $source, int $srcWidth, int $srcHeight, int $dstWidth, int $dstHeight): \GdImage
    {
        // Create a new true color image
        $destination = imagecreatetruecolor($dstWidth, $dstHeight);

        if ($destination === false) {
            throw new RuntimeException("Failed to create destination image ({$dstWidth}x{$dstHeight})");
        }

        // Preserve transparency for PNG/GIF
        imagealphablending($destination, false);
        imagesavealpha($destination, true);

        // Fill with white background (for JPEGs we don't need transparency)
        $white = imagecolorallocate($destination, 255, 255, 255);
        imagefill($destination, 0, 0, $white);

        // Resample the image
        $result = imagecopyresampled(
            $destination,
            $source,
            0, 0,           // destination x, y
            0, 0,           // source x, y
            $dstWidth,      // destination width
            $dstHeight,     // destination height
            $srcWidth,      // source width
            $srcHeight      // source height
        );

        if ($result === false) {
            imagedestroy($destination);
            throw new RuntimeException('Failed to resample image');
        }

        return $destination;
    }

    /**
     * Calculate new dimensions maintaining aspect ratio.
     *
     * @return array{0: int, 1: int}
     */
    private function calculateNewDimensions(int $width, int $height): array
    {
        $maxDim = self::MAX_DIMENSION;

        // Guard against zero dimensions
        if ($width <= 0 || $height <= 0) {
            return [1, 1];
        }

        if ($width <= $maxDim && $height <= $maxDim) {
            return [$width, $height];
        }

        if ($width > $height) {
            $newWidth = $maxDim;
            $newHeight = (int) round($height * ($maxDim / $width));
        } else {
            $newHeight = $maxDim;
            $newWidth = (int) round($width * ($maxDim / $height));
        }

        // Ensure minimum dimensions
        $newWidth = max(1, $newWidth);
        $newHeight = max(1, $newHeight);

        return [$newWidth, $newHeight];
    }

    /**
     * Compress image to JPEG format, adjusting quality to meet size target.
     */
    private function compressToJpeg(\GdImage $image): string
    {
        $quality = 85;

        while ($quality >= self::MIN_QUALITY) {
            ob_start();
            $success = imagejpeg($image, null, $quality);
            $data = ob_get_clean();

            if (! $success || $data === false) {
                throw new RuntimeException("Failed to encode image as JPEG at quality {$quality}");
            }

            $base64 = base64_encode($data);
            $estimatedSize = strlen($base64) * 3 / 4;

            Log::debug('JPEG compression attempt', [
                'quality' => $quality,
                'size_mb' => round($estimatedSize / 1024 / 1024, 2),
            ]);

            if ($estimatedSize <= self::TARGET_SIZE) {
                return $base64;
            }

            // Reduce quality and try again
            $quality -= 10;
        }

        // Return whatever we got at minimum quality
        ob_start();
        imagejpeg($image, null, self::MIN_QUALITY);
        $data = ob_get_clean();

        if ($data === false) {
            throw new RuntimeException('Failed to encode image as JPEG at minimum quality');
        }

        return base64_encode($data);
    }
}

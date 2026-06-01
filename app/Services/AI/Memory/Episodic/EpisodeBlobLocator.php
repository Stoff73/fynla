<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use Illuminate\Support\Facades\Storage;

/** Resolve an episodic blob across hot (episodic/) and cold (episodic-cold/) storage. */
final class EpisodeBlobLocator
{
    public function resolve(string $relativePath): ?string
    {
        $disk = Storage::disk('local');
        if ($disk->exists($relativePath)) {
            return $relativePath;
        }
        $cold = str_replace('episodic/', 'episodic-cold/', $relativePath);
        if ($cold !== $relativePath && $disk->exists($cold)) {
            return $cold;
        }

        return null;
    }

    public function get(string $relativePath): ?string
    {
        $resolved = $this->resolve($relativePath);

        return $resolved === null ? null : Storage::disk('local')->get($resolved);
    }
}

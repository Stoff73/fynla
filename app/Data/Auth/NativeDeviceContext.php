<?php

declare(strict_types=1);

namespace App\Data\Auth;

final readonly class NativeDeviceContext
{
    public function __construct(
        public string $platform,
        public string $deviceLabel,
        public string $appVersion,
        public string $appBuild,
    ) {}
}

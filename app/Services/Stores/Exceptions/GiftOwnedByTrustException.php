<?php

declare(strict_types=1);

namespace App\Services\Stores\Exceptions;

use RuntimeException;

/** A gift that is a trust's settlement is changed through the trust (W-0528). */
class GiftOwnedByTrustException extends RuntimeException {}

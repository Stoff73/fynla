<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Raised by SpouseLinkingService::guardInviteRate when a user has asked for
 * more spouse invitations in an hour than the household limit allows
 * (W-0349). Each surface turns it into its own reply: a 429 on Family
 * settings, a sentence in Fyn's chat.
 */
class SpouseInviteLimitException extends \RuntimeException {}

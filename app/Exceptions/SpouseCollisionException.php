<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Raised by SpouseLinkingService when the target spouse email is already
 * bound to another Fynla household (i.e. `users.spouse_id` is set to a
 * third party, not the current user).
 *
 * Distinguished from \InvalidArgumentException so that
 * CoordinatingAgent::handleCaptureSpouseDetails can branch the response
 * shape (returns `error_type => 'spouse_collision'`) and the onboarding
 * director can emit a targeted terminal error rather than the generic
 * grouped_extract retry copy.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M13
 */
class SpouseCollisionException extends \RuntimeException
{
}

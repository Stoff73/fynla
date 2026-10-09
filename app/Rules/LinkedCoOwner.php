<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A linked co-owner must be the signed-in user's spouse, linked both ways.
 *
 * Forms name the co-owner from the editor's side (SharedOwnership::fromEditor):
 * the primary owner names the joint owner, the joint owner names the primary.
 * Either way it is the editor's reciprocal spouse (User::hasReciprocalSpouseLink,
 * the one canonical rule, as SavingsStore applies it). Without this any user id
 * passed `exists:users,id`, so a record could be handed to a stranger, who could
 * then see, change and remove it. A co-owner off the platform has no id and
 * goes by `joint_owner_name`.
 */
final class LinkedCoOwner implements ValidationRule
{
    public function __construct(private readonly ?User $editor) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->editor === null || ! is_numeric($value) || ! $this->editor->hasReciprocalSpouseLink((int) $value)) {
            $fail('The joint owner must be securely linked to your household.');
        }
    }
}

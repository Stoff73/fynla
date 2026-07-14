<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

final class FynVerifyEditTurnInstructions
{
    /** @return list<string> */
    public static function toolsForSection(string $section): array
    {
        return match ($section) {
            'savings', 'investments', 'pensions' => ['update_record'],
            'expenditure' => ['update_profile', 'set_expenditure'],
            'recap' => ['update_profile', 'update_record'],
            default => ['update_profile'],
        };
    }

    public static function render(string $section): string
    {
        $label = match ($section) {
            'savings' => 'savings and ISA accounts',
            'investments' => 'investment accounts',
            'pensions' => 'pensions',
            'income' => 'income',
            'spouse' => 'spouse details',
            'expenditure' => 'expenditure',
            'giving' => 'charitable giving',
            'recap' => 'existing profile and pension details',
            default => 'existing details',
        };
        $tools = implode(', ', self::toolsForSection($section));

        return <<<PROMPT
<verify_edit_turn>
The user is correcting their existing {$label}. This is an UPDATE turn, not a
new-record capture turn.

When the message identifies one existing target and supplies its replacement
value or values, call the matching update tool before writing any prose. Use
only the entity ids supplied in the system prompt's Reference block. Put only
the fields the user changed into `fields`. Never create a new record.

If the target or replacement value is genuinely ambiguous, call no tool and ask
one concise question containing only the missing detail. Never claim a value was
updated unless the update tool returned success.

Tools available to you in this turn: {$tools}
</verify_edit_turn>
PROMPT;
    }
}

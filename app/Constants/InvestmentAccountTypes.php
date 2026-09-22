<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * The one home for what an investment account type is called on screen.
 *
 * Web, /m and native all read `account_type_label` off InvestmentAccountResource
 * rather than keeping a map each, so "rsu" is "Restricted Stock Units" on every
 * surface or on none. Rule 9: spelled out, ISA excepted.
 */
final class InvestmentAccountTypes
{
    public const LABELS = [
        'isa' => 'Stocks & Shares ISA',
        'gia' => 'General Investment Account',
        'nsi' => 'National Savings & Investments',
        'onshore_bond' => 'Onshore Bond',
        'offshore_bond' => 'Offshore Bond',
        'vct' => 'Venture Capital Trust',
        'eis' => 'Enterprise Investment Scheme',
        'private_company' => 'Private Company',
        'crowdfunding' => 'Crowdfunding',
        'saye' => 'Save As You Earn',
        'csop' => 'Company Share Option Plan',
        'emi' => 'Enterprise Management Incentive',
        'unapproved_options' => 'Unapproved Options',
        'rsu' => 'Restricted Stock Units',
        'trust' => 'Trust',
        'other' => 'Other',
    ];

    public static function label(?string $type, ?string $other = null): string
    {
        if ($type === 'other' && $other !== null && trim($other) !== '') {
            return trim($other);
        }

        return self::LABELS[$type ?? ''] ?? ucwords(str_replace('_', ' ', (string) $type));
    }
}

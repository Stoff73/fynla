<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mortgage Defaults
    |--------------------------------------------------------------------------
    |
    | Default values used when creating new mortgages where optional fields
    | are not provided by the user.
    |
    */

    'default_lender_name' => 'To be completed',
    'default_mortgage_type' => 'repayment',
    'default_interest_rate' => 0.0000,
    'default_rate_type' => 'fixed',
    'default_term_years' => 25,
    'default_term_months' => 300, // 25 years

];

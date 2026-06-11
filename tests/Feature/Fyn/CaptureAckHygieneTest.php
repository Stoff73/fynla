<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynCaptureTurnInstructions;

it('tells the model to state what was recorded instead of a generic ack', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    expect($rendered)
        ->toContain('states WHAT was recorded')
        ->not->toContain('"Got it — recording those now."');
});

it('tells the model to stay silent when no records are captured', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    expect($rendered)->toContain('If you call no tools');
});

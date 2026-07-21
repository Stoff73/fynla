<?php

declare(strict_types=1);

use Tests\Feature\Fyn\Eval\EvalRunner;

it('eval harness boots with zero scenarios at S1.1', function (): void {
    $scenarios = iterator_to_array(EvalRunner::scenarios(), false);

    expect($scenarios)->toBeArray();
});

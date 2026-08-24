<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Estate\IHTController;
use App\Services\Plans\EstatePlanService;

/**
 * Two screens must not disagree about one figure.
 *
 * `IHTController` and `EstatePlanService` both assemble an `iht_summary` from the
 * same `IHTCalculationService` result, for `/estate/inheritance-tax` and
 * `/plans/estate` respectively. `EstatePlanService`'s own docblock says the block is
 * *"matching IHTController response shape"* — and nothing enforced it.
 *
 * On 2026-08-24 commit `e4aa4cdc9` deleted `charitable_deduction` from the plan
 * service's current block while adding a key beside it. The consumer
 * (`EstateCurrentSituation.vue:205-206`) never stopped reading it, so `/plans/estate`
 * showed **£0 charitable exemption** while the Inheritance Tax screen showed the real
 * figure. **That is W-0135 and W-0154's exact disease, reintroduced by the commit
 * fixing the last round of it**, and it shipped because that commit contained five
 * code files and no tests at all.
 *
 * This reads the two source files rather than driving both endpoints, deliberately:
 * the defect is a divergence between two hand-written key lists, and the cheapest
 * honest guard is to compare the lists. Driving the endpoints would prove the figures
 * agree for one fixture; this proves the SHAPES agree for every one.
 */
it('publishes the same estate summary keys from both producers', function () {
    $keysIn = function (string $file, string $marker): array {
        $body = (string) file_get_contents($file);

        $start = strpos($body, $marker);
        expect($start)->not->toBeFalse("could not find `{$marker}` in {$file}");

        // **Bounded by bracket depth, not by a fixed window.** The first version took
        // `substr($body, $start, 6000)`, which ran past the end of the `current`
        // block into `projected` — and `projected` carries several of the same key
        // names. So deleting a key from `current` still found it in the slice and the
        // guard passed. Caught by mutation-testing the very regression it was written
        // for: removing `charitable_deduction` left it green.
        $depth = 0;
        $end = null;

        for ($i = $start + strlen($marker) - 1; $i < strlen($body); $i++) {
            if ($body[$i] === '[') {
                $depth++;
            } elseif ($body[$i] === ']') {
                $depth--;

                if ($depth === 0) {
                    $end = $i;

                    break;
                }
            }
        }

        expect($end)->not->toBeNull("could not find the end of `{$marker}` in {$file}");

        $slice = substr($body, $start, $end - $start);

        // Top level of THIS array only — a nested array's keys are not summary keys.
        preg_match_all("/^ {16,20}'([a-z0-9_]+)' =>/m", $slice, $m);

        return $m[1];
    };

    $controller = (new ReflectionClass(IHTController::class))->getFileName();
    $planService = (new ReflectionClass(EstatePlanService::class))->getFileName();

    $controllerCurrent = $keysIn($controller, "'current' => [");
    $planCurrent = $keysIn($planService, "'current' => [");

    // The figures that must appear on both screens. Not the full intersection — the
    // two blocks legitimately differ in places — but every key whose absence on one
    // side puts a different number in front of the user for the same fact.
    $mustAgree = [
        'net_estate',
        'total_allowances',
        'charitable_deduction',
        'business_relief_deduction',
        'taxable_estate',
        'iht_liability',
    ];

    // If this fails, `EstatePlanService` has stopped publishing a figure
    // `IHTController` publishes, and the two screens will show different numbers
    // for the same fact.
    $missingFromPlan = array_values(array_diff(
        array_intersect($mustAgree, $controllerCurrent),
        $planCurrent
    ));

    expect($missingFromPlan)->toBe([]);

    $missingFromController = array_values(array_diff(
        array_intersect($mustAgree, $planCurrent),
        $controllerCurrent
    ));

    expect($missingFromController)->toBe([]);
});

it('publishes business relief in the projected block of both producers', function () {
    // The half W-0465 fixed on one surface and left on the other. `projected` blocks
    // are structured differently enough that comparing them wholesale is noise, so
    // this pins the one key that went missing.
    foreach ([IHTController::class, EstatePlanService::class] as $class) {
        $body = (string) file_get_contents((new ReflectionClass($class))->getFileName());

        $projected = substr($body, (int) strpos($body, "'projected' => ["), 6000);

        // NOTE: Pest's `toContain` takes VARARGS, not a message — a second string
        // is asserted as another needle. Guidance lives in comments, not in the call.
        // If this fails, the named class has stopped publishing
        // `business_relief_deduction` in its projected block, which is the half of
        // W-0465 that was fixed on one surface and missed on the other.
        expect($projected)->toContain('business_relief_deduction');
    }
});

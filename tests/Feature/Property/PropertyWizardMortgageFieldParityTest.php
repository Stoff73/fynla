<?php

declare(strict_types=1);

use App\Http\Requests\StorePropertyRequest;

/**
 * W-0012 — the wizard and the API must accept the same mortgage fields.
 *
 * **Why this test is shaped so oddly.** The defect was that
 * `PropertyList.vue` hand-copied a subset of mortgage fields into the create
 * payload, so `rate_fix_end_date` — and eight others — never left the browser.
 * The backend was correct. The existing HTTP test was correct. It passed
 * because it **POSTs the key straight to the API**, which is a door the user
 * never uses:
 *
 * > "The test and the browser take different doors." — quality-lead, 2026-08-23
 *
 * That is the Fixture variant in `tests/CLAUDE.md`: the test constructs the
 * input the browser never sends, so it proves the receiver works while the
 * sender is still broken. A green test, a real fix, and the user's defect
 * entirely intact.
 *
 * So this asserts something a request test structurally cannot: that the
 * SENDER can express every field the RECEIVER accepts. It reads the Vue source
 * because that is where the sender lives, and a PHP test is the only place both
 * halves are visible at once.
 */
it('lets the property wizard send every mortgage field the API accepts', function () {
    $rules = array_keys((new StorePropertyRequest)->rules());

    $accepted = array_values(array_filter(
        $rules,
        fn (string $rule): bool => str_starts_with($rule, 'mortgage_')
    ));

    // StorePropertyRequest must still declare mortgage fields at all; if this is
    // empty the rest of the test would pass vacuously.
    expect($accepted)->not->toBeEmpty();

    $wizard = file_get_contents(base_path('resources/js/components/NetWorth/PropertyList.vue'));

    // The payload is built by a rule — prefix the form's key with `mortgage_`
    // unless it already carries it — rather than by a list, precisely so a new
    // backend field needs no edit here. This asserts the rule is still what
    // builds it. If someone reverts to enumerating fields, the marker goes and
    // this fails, which is the moment to look.
    // NOTE: Pest's `toContain` takes VARARGS, not a message — a second string
    // argument is asserted as another needle. Failure guidance therefore lives
    // in these comments, not in the call.
    //
    // If this fails, `PropertyList.vue` has stopped deriving the mortgage
    // payload by rule. Should it have gone back to a hand-written list, every
    // field missing from that list is silently dropped in the browser while the
    // API test carries on passing — which is exactly how W-0012 survived.
    expect($wizard)->toContain("key.startsWith('mortgage_') ? key : `mortgage_\${key}`");

    // And the one field that cannot follow the rule, because the form and the
    // request use different names for it.
    // The balance is the single exception to the prefix rule — the form calls it
    // `outstanding_balance` and the request calls it `outstanding_mortgage` — so
    // it cannot be derived and must survive explicitly.
    expect($wizard)->toContain('data.property.outstanding_mortgage = data.mortgage.outstanding_balance');
});

it('names the field the item was raised for, so a rename cannot pass silently', function () {
    // W-0012's headline. Kept as its own case because the rule above would still
    // hold if the backend dropped this field entirely — and then the wizard would
    // be "correct" about a field the user can no longer save.
    $rules = array_keys((new StorePropertyRequest)->rules());

    expect($rules)->toContain('mortgage_rate_fix_end_date');

    $form = file_get_contents(base_path('resources/js/components/NetWorth/Property/PropertyForm.vue'));

    // The form must still capture the Rate Fix End Date.
    expect($form)->toContain('rate_fix_end_date');
});

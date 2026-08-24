<?php

declare(strict_types=1);

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdateMortgageRequest;

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
    // **This test previously did not test what it is named after**, and quality-lead
    // said so: `$accepted` was computed, asserted non-empty, and then never used
    // again. The weight was carried by a source-text match, which catches a literal
    // revert to the hand-copied list and nothing else — not a semantically identical
    // rewrite, not the derivation becoming dead, not a receiver field the sender
    // cannot emit. That is the Decoy variant in `tests/CLAUDE.md`: a case named after
    // a property it does not check.
    //
    // It matters more than a usual weak test, because W-0012 was REJECTED for a test
    // that took a different door from the browser. The replacement took a third door.
    //
    // This version actually compares the two sides: it applies the sender's rule to
    // the field names the form emits, and asserts the result covers every
    // `mortgage_*` field the request declares.
    $accepted = array_values(array_filter(
        array_keys((new StorePropertyRequest)->rules()),
        fn (string $rule): bool => str_starts_with($rule, 'mortgage_')
    ));

    expect($accepted)->not->toBeEmpty();

    // The names the mortgage form emits, read from its own field declaration rather
    // than hand-listed — a field added to the form and not to the request (or the
    // reverse) is exactly what this is for.
    $form = (string) file_get_contents(base_path('resources/js/components/NetWorth/Property/PropertyForm.vue'));

    $start = strpos($form, 'mortgageForm: {');
    expect($start)->not->toBeFalse();

    $depth = 0;
    $end = null;

    for ($i = $start + strlen('mortgageForm: '); $i < strlen($form); $i++) {
        if ($form[$i] === '{') {
            $depth++;
        } elseif ($form[$i] === '}') {
            $depth--;

            if ($depth === 0) {
                $end = $i;

                break;
            }
        }
    }

    expect($end)->not->toBeNull();

    preg_match_all('/^\s*([a-z0-9_]+):/m', substr($form, $start, $end - $start), $m);
    $emitted = $m[1];

    expect($emitted)->not->toBeEmpty();

    // The rule `PropertyList.vue` applies: prefix unless already prefixed. Expressed
    // here so the assertion is about the CORRESPONDENCE rather than about the text
    // that implements it.
    $sendable = array_map(
        fn (string $key): string => str_starts_with($key, 'mortgage_') ? $key : 'mortgage_'.$key,
        $emitted
    );

    // **The assertion with content is the other direction.** Asking "is every accepted
    // field the form collects expressible" is tautological — `$sendable` is DERIVED
    // from the form keys, so the intersection can never disagree with itself. (The
    // first draft of this line was exactly that: `array_diff(X, X)`, which cannot
    // fail. Written while correcting a different vacuous assertion, which is how easy
    // it is.)
    //
    // What CAN fail is a form field whose derived name the request does not accept:
    // the user fills it in, the wizard sends it, validation drops it silently. That is
    // W-0012's disease in its general form.
    $knownUnsent = [
        // Collected for the mortgage's own endpoints, not for property creation.
        'mortgage_id', 'mortgage_country', 'mortgage_notes',
        // The one true exception: the form calls it `outstanding_balance` and the
        // request calls it `outstanding_mortgage`, mapped explicitly in the wizard.
        'mortgage_outstanding_balance',
    ];

    $sentButNotAccepted = array_values(array_diff($sendable, $accepted, $knownUnsent));

    expect($sentButNotAccepted)->toBe([]);

    // And the field W-0012 was raised for is genuinely expressible, so this cannot
    // pass by both sides being empty.
    expect($sendable)->toContain('mortgage_rate_fix_end_date');

    // The marker still guards against a literal revert to the hand-copied list. Kept
    // as a SECOND assertion rather than the only one.
    $wizard = (string) file_get_contents(base_path('resources/js/components/NetWorth/PropertyList.vue'));

    expect($wizard)->toContain("key.startsWith('mortgage_') ? key : `mortgage_\${key}`");
    expect($wizard)->toContain('data.property.outstanding_mortgage = data.mortgage.outstanding_balance');
});

it('sends mortgage changes on the EDIT path, which had no door at all', function () {
    // quality-lead, re-certification: "A user editing mortgage details on an existing
    // property still loses every one of them." The create path was fixed and the
    // update branch still PUT only `data.property`.
    //
    // It cannot go through the property endpoint — `UpdatePropertyRequest` declares
    // zero `mortgage_*` rules, so the keys would be stripped at validation. This
    // asserts the edit branch reaches the mortgage's own endpoint instead.
    $wizard = (string) file_get_contents(base_path('resources/js/components/NetWorth/PropertyList.vue'));

    expect($wizard)->toContain('api.put(`/mortgages/${mortgageId}`')
        ->and($wizard)->toContain('api.post(`/properties/${data.property.id}/mortgages`');

    // The form must carry the id, or the update has nothing to target.
    $form = (string) file_get_contents(base_path('resources/js/components/NetWorth/Property/PropertyForm.vue'));

    expect($form)->toContain('this.mortgageForm.id = mortgage.id');

    // And the receiving endpoint must still accept the field W-0012 is about.
    expect(array_keys((new UpdateMortgageRequest)->rules()))->toContain('rate_fix_end_date');
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

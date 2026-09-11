<?php

declare(strict_types=1);

use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\Validator;

/**
 * W-0542 follow-up. `resources/js/utils/registrationRules.js` mirrors
 * RegisterRequest's rules and messages by hand so the form can show every
 * error at once. Nothing else pins the two, so a server wording change would
 * drift silently. This test runs the real request rules over a bad payload and
 * asserts every message it produces appears verbatim in the client file.
 */
it('keeps the client registration messages identical to RegisterRequest', function () {
    $request = new RegisterRequest;
    $client = file_get_contents(base_path('resources/js/utils/registrationRules.js'));

    // The client interpolates its minimum length; resolve it from the server rule and pin the constant too.
    $min = (int) substr(collect($request->rules()['password'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'min:')), 4);
    expect($client)->toContain("PASSWORD_MIN_LENGTH = {$min};");
    $client = str_replace('${PASSWORD_MIN_LENGTH}', (string) $min, $client);

    $payloads = [
        ['first_name' => '', 'surname' => '', 'email' => '', 'password' => '', 'password_confirmation' => ''],
        ['first_name' => 'A', 'surname' => 'B', 'email' => 'nope', 'password' => 'abc', 'password_confirmation' => 'abd'],
    ];

    $seen = [];
    foreach ($payloads as $payload) {
        $errors = Validator::make($payload, $request->rules(), $request->messages())->errors();
        foreach (['first_name', 'surname', 'email', 'password'] as $field) {
            foreach ($errors->get($field) as $message) {
                $seen[] = $message;
                expect($client)->toContain($message);
            }
        }
    }

    // The complexity regex is the same expression on both sides.
    $regex = collect($request->rules()['password'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'regex:'));
    expect($client)->toContain(substr($regex, strlen('regex:/'), -1));

    expect(count(array_unique($seen)))->toBeGreaterThanOrEqual(6);
});

<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * W-0261, second fault — the raw INSERT statement was rendered to the end user.
 *
 * A constraint violation on the holdings form put this on the page:
 *
 *   SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'dividend_yield'
 *   cannot be null (Connection: mysql, SQL: insert into `holdings` (`asset_type`,
 *   `sub_type`, `security_name`, `ticker`, `isin`, `allocation_percent`, …
 *
 * That is schema disclosure — table name, connection name, full column list — and
 * it is a separate fault from the validation bug that triggered it. `Handler`
 * returned `$exception->getMessage()` and only sanitised it behind
 * `! config('app.debug')`.
 *
 * TEST DESIGN, two decisions worth stating:
 *
 * 1. `app.debug` is forced ON. The whole point is the debug path — a run with debug
 *    off passes on the OLD code and proves nothing, because the old code already
 *    sanitised there. Debug is true on every developer machine and on any server
 *    where it has been left on, and the disclosure is a property of the exception,
 *    not of the environment.
 *
 * 2. The handler is driven through `ExceptionHandler::render()` rather than through
 *    a route. `render()` IS the production entry point — it runs the `renderable`
 *    callback registered in `Handler::register()`, which is the code under test. A
 *    route declared inside a test cannot be used here: `routes/web.php` ends in an
 *    SPA catch-all, so anything registered later is appended behind it and never
 *    matches (verified — such a request returns the SPA's HTML with a 200).
 */
beforeEach(function () {
    config(['app.debug' => true]);

    $this->handler = app(ExceptionHandler::class);

    // `api/*` is what Handler::register()'s renderable closure keys on.
    $this->apiRequest = Request::create('/api/investment/holdings', 'POST');
});

function holdingsConstraintViolation(): QueryException
{
    return new QueryException(
        'mysql',
        'insert into `holdings` (`asset_type`, `sub_type`, `security_name`, `ticker`, `isin`, `allocation_percent`, `dividend_yield`) values (?, ?, ?, ?, ?, ?, ?)',
        ['fund', 'equity_fund', 'Fundsmith Equity', 'FUND', 'GB00B41YBW71', 36.8, null],
        new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'dividend_yield' cannot be null")
    );
}

it('never returns database exception text to the client, even with debug on', function () {
    $exception = holdingsConstraintViolation();

    // The message the OLD code forwarded verbatim. If this stops containing the
    // statement, the test has stopped testing anything.
    expect($exception->getMessage())->toContain('SQLSTATE')
        ->and($exception->getMessage())->toContain('insert into');

    $body = $this->handler->render($this->apiRequest, $exception)->getContent();

    expect($body)->not->toContain('SQLSTATE')
        ->and($body)->not->toContain('insert into')
        ->and($body)->not->toContain('holdings')
        ->and($body)->not->toContain('dividend_yield')
        ->and($body)->not->toContain('Connection: mysql')
        ->and($body)->not->toContain('Integrity constraint violation');
});

it('returns a civil message a user can act on', function () {
    $response = $this->handler->render($this->apiRequest, holdingsConstraintViolation());

    expect($response->getStatusCode())->toBe(500);

    $payload = json_decode($response->getContent(), true);

    expect($payload['success'])->toBeFalse()
        ->and($payload['message'])->toBe(
            'We could not save that. Please check the form and try again, or contact support if it keeps happening.'
        );
});

it('sanitises a bare PDOException too', function () {
    // Not every database failure is a QueryException — a connection error carries
    // credentials-adjacent detail in exactly the same way.
    $response = $this->handler->render(
        $this->apiRequest,
        new PDOException('SQLSTATE[HY000] [1045] Access denied for user \'fynla\'@\'localhost\'')
    );

    expect($response->getContent())->not->toContain('Access denied')
        ->and($response->getContent())->not->toContain('SQLSTATE');
});

it('still surfaces ordinary exception messages in debug mode', function () {
    // The sanitisation is scoped to database exceptions. Narrowing it further would
    // be a second change wearing this one's clothes — normal debugging output is
    // untouched.
    $response = $this->handler->render(
        $this->apiRequest,
        new RuntimeException('A perfectly ordinary failure')
    );

    $payload = json_decode($response->getContent(), true);

    expect($payload['message'])->toBe('A perfectly ordinary failure');
});

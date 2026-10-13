<?php

declare(strict_types=1);

use App\Constants\GateRoutes;
use App\Services\AI\NavigationCommand;

/*
 * T6 (walk fix plan B2.5, F3, F4): navigation is decided on the server, for
 * every surface. Only a plain command with no question in it navigates, and
 * the whole rest of the message must name a page (words match whole).
 */

it('navigates on a plain command naming a page', function (string $message, string $destination) {
    expect(NavigationCommand::match($message))->toBe($destination);
})->with([
    'F4 typed request' => ['ok take me to my tax strategy', GateRoutes::TAX_STRATEGY],
    'savings' => ['take me to my savings', GateRoutes::SAVINGS],
    'page suffix' => ['Go to the Retirement page', GateRoutes::RETIREMENT],
    'show me' => ['Show me my investments', GateRoutes::INVESTMENT],
    'open' => ['open the will builder', GateRoutes::WILL_BUILDER],
    'navigate to' => ['navigate to my actions.', GateRoutes::ACTIONS],
    'please' => ['Please take me to the dashboard', GateRoutes::DASHBOARD],
    'trailing please' => ['go to income please', GateRoutes::INCOME],
    'what-if' => ['open what-if scenarios', GateRoutes::WHAT_IF],
    'plan before module' => ['take me to my retirement plan', GateRoutes::RETIREMENT_PLAN],
    // Carried over from the deleted web matcher's tests.
    'old: show me my protection' => ['show me my protection', GateRoutes::PROTECTION],
    'old: take me to my pensions' => ['take me to my pensions', GateRoutes::RETIREMENT],
    'old: go to estate planning' => ['go to estate planning', GateRoutes::ESTATE],
    'old: open settings' => ['open settings', GateRoutes::SETTINGS],
]);

it('never navigates on a question, a statement or a sentence that only mentions a page', function (string $message) {
    expect(NavigationCommand::match($message))->toBeNull();
})->with([
    'F3a working then page' => ['Projected income of £62,894. Show your working, then take me to the Income page'],
    'F3c show the working' => ['My retirement page says £484,572 at 60. Show the working.'],
    'F3d show the working' => ['Projected income of £62,894 a year. Show the working.'],
    'question form' => ['Can you open the Save Tax page for me?'],
    'question mark' => ['take me to my savings?'],
    'not a page' => ['show me the working'],
    'a write' => ['open a new ISA'],
    'page plus a request' => ['go to the dashboard and tell me my net worth'],
    'substring of a page word' => ['open cashflow'],
    'topic mention' => ['I want to look at my pension'],
    // Carried over from the deleted web matcher's tests.
    'old: question about savings' => ['am i protected for my savings'],
    'old: complaint' => ['why didnt you show me my pensions'],
    'old: overview request' => ['give me an overview of my estate'],
    'old: review later' => ['i will review my protection later'],
    'old: polite question' => ['can you reopen my pensions screen'],
]);

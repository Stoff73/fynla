<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;

it('detects direct advice phrasings', function (string $message) {
    expect(QuerySchemas::isAdviceShaped($message))->toBeTrue();
})->with([
    'Should I contribute more to my pension?',
    'What about my emergency fund?',
    'How much can I afford?',
    'Am I on track for retirement?',
    'Can you explain the ISA allowance?',
    'Why is my pension projection low?',
    'Recommend a savings rate please',
    'I need advice on my investments',
    'Compare SIPP vs workplace pension',
    'Run a projection for age 65',
    'Give me a forecast for next year',
]);

it('does not flag clean data-entry phrasings as advice-shaped', function (string $message) {
    expect(QuerySchemas::isAdviceShaped($message))->toBeFalse();
})->with([
    'Add my Nationwide cash ISA at 5000',
    'Create a pension with Scottish Widows value 50k',
    'Record a gift of 3000 to my daughter',
    'Store a life event wedding 2027 cost 15000',
]);

it('flags mixed-intent question-mark messages as advice-shaped', function () {
    expect(QuerySchemas::isAdviceShaped('Add my Nationwide ISA 5k, thoughts?'))->toBeTrue()
        ->and(QuerySchemas::isAdviceShaped('Add my SIPP 50k DC. Is that enough?'))->toBeTrue()
        ->and(QuerySchemas::isAdviceShaped('Log my pension Scottish Widows 50k. What do you think?'))->toBeTrue();
});

it('counts any question mark as a question (CSJ 2026-10-12: a question always goes to the answer process)', function () {
    // The single-clause carve-out ("Can I add it?" was not a question) was
    // never wired; every gate already treated a trailing "?" as a question
    // through WriteIntentClassifier::looksLikeQuestion, which this replaces.
    expect(QuerySchemas::isAdviceShaped('Can I add it?'))->toBeTrue()
        ->and(QuerySchemas::isAdviceShaped('OK?'))->toBeTrue();
});

it('is case-insensitive', function () {
    expect(QuerySchemas::isAdviceShaped('SHOULD I SAVE MORE'))->toBeTrue()
        ->and(QuerySchemas::isAdviceShaped('should i save more'))->toBeTrue();
});

/*
 * Brett's production walk of 11 October 2026 (fynla.org, user 812; fix plan
 * October/October12Updates/walk-2026-10-11-fix-plan.md, section 8). Every one
 * of these asked for an answer; the gates before the model turned several
 * into writes, a "none" declaration or a capture continuation.
 */
it("reads each of Brett's questions as a question", function (string $message) {
    expect(QuerySchemas::isAdviceShaped($message))->toBeTrue();
})->with([
    'F5 #3861' => 'He has no savings outside his cash ISA, so his taxable interest is zero. Assume I move the whole £38,000. Now give me the saving for the gift, for making it joint, and for the ISA, and tell me which wins.',
    'F19 #3869' => "I've got death in service of 4x salary through Monzo and nothing else. Dev has the Teachers' pension death grant. No income protection, no critical illness. If I died or couldn't work, would the family be ok? What cover would you look at and roughly how much?",
    'F22 #3877' => "I'd like to retire at 60, not 67. Dev would carry on teaching until 65. Can we afford it on what we've got, and what would I need to put away each month to make it work? Please use my actual figures.",
    'M8 #3961' => "I have a daughter, she's 27. If I died tomorrow and she inherited everything, how much inheritance tax would there be, and does she get the extra allowance for the house?",
    'P1 #3963' => 'My Chase savings of £38,000 at 4.5% are in my name and I pay 40% on the interest. Dev earns £48,000 and has no taxable savings interest. Give me the yearly tax saving for three options: gift the whole lot to Dev, make it a joint account, or move £12,000 into my ISA. Show the working for each.',
    'P2 #3965' => "Chase is on file at 4.5%, that's £1,710 a year. Now give me the yearly tax saving for each of the three options, gift to Dev, joint, or £12,000 into my ISA, with the working.",
    'P3 #3967' => "I didn't ask you to record anything, please don't. It's a hypothetical: how much tax a year is due on £1,710 of interest if it's all mine, if it's all Dev's, and if it's split equally between us? Three numbers with the working.",
    'P5 #3971' => "Run a what-if for me: if I paid an extra £20,000 a year into my Aviva pot from now until 60, what would the pot and my net worth be at 60 compared with today's projection?",
    'P7 #3973' => "If I died tomorrow and everything I own went to my husband Dev, would there be any tax to pay on what I leave? I've been shown a figure of £3,900 on £9,750. Is that right? Explain the working either way.",
    'Q5 #3997' => "Now that we let the Woking flat, how is my half of the rent taxed this year? Can the mortgage interest and the agent's fee be deducted, and would the household pay less if the flat were held mainly by the lower earner? Figures please.",
    'Q5 #3999' => "Rules question, nothing to record. A higher-rate taxpayer gets £8,700 a year as her half of the rent on a jointly owned let flat. Her share of the interest-only mortgage interest is £4,680 a year and of the agent's fee £870. How much tax does she pay on that rent, how is the mortgage interest relieved, and would the household pay less if the other owner, a basic-rate taxpayer, held most of it? Figures please.",
    'Q6 #4003' => "If I stop work at 60 and want £70,000 a year after that, how far short am I on what's on file, and what would I need to put away each month from now to close it? Show the working.",
    'Q7 #4009' => 'Pure arithmetic, nothing to record. Projected £62,894 a year at 60 against a wanted £70,373 a year. What is the gap per year and per month, what extra pot does the gap need at a 4% withdrawal rate, and what monthly saving over 19 years at 5% a year growth builds that extra pot? Show the working.',
    'Q7 #4013' => 'Then work it through: projected £62,894 a year at 60 against a wanted £70,373. Gap per year and per month, the extra pot that gap needs at a 4% withdrawal rate, and the monthly saving over 19 years at 5% growth that builds it. Show the working.',
    'Q8 #4015' => "Rules and numbers only, nothing to record. On what's on file, if both of us died next year and everything went to the kids, what would the death tax bill be, and what are the three best things to do about it? Figures please.",
    'Q10 #4033' => 'Hang on, I told you earlier the Chase account was £38,000 but I think I may have said £83,000 at some point. Which figure do you have on file, and which should it be?',
]);

it("leaves Brett's statements and everyday answers as statements", function (string $message) {
    expect(QuerySchemas::isAdviceShaped($message))->toBeFalse();
})->with([
    'M6 #3946' => 'I have £60,000 in an easy access account with Marcus paying 4.3%',
    'P8 #3977' => 'Please add my two children: Maya, who is 11, and Arjun, who is 8. They both depend on me and Dev.',
    // A typed answer at a setup form fills that form (offerTypedAnswerForm),
    // so a statement that merely contains a question word must stay a statement.
    'unsure amount' => "I'm not sure how much exactly, about £20,000",
    'state pension forecast' => 'My State Pension forecast is £11,500 a year',
    'so am I' => 'Dev is a teacher and so am I',
    'gift income' => 'My parents give me £200 a month',
    'when clause' => 'When I sold my flat I put £50,000 into savings',
    'acceptance' => 'Yes please, set it up.',
    'do it' => 'Yes, do it',
    'capture answer' => "3% and it's matched",
    'entity statement' => 'Halifax ISA with ten thousand in it',
]);

it('reads the openings users drop the question mark from', function (string $message) {
    expect(QuerySchemas::isAdviceShaped($message))->toBeTrue();
})->with([
    'explain salary sacrifice please',
    "what's salary sacrifice",
    'Tell me how the tapered allowance works',
    'show me my pension',
    'Is that right',
    'Okay, which wins',
    'Talk me through the Inheritance Tax',
    'Work out what I would save',
    'Define a SIPP',
]);

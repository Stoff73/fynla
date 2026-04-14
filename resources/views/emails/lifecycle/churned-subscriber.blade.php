@extends('emails.lifecycle._layout')
@section('title', 'Thank you for being a Fynla subscriber')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>You were a Fynla subscriber@if ($subscriptionDuration) for {{ $subscriptionDuration }}@endif and we're really sorry to see you go. Thank you for trusting us with your financial planning during that time.</p>

    <p>We're a small team trying to build the best UK financial planning tool we can, and the only way we get better is by hearing from people who chose to leave. If you have a moment, what was the main reason?</p>

    @include('emails.lifecycle._quick-picks', [
        'buttons' => $feedbackUrls,
        'labels' => [
            'too_expensive' => 'Too expensive',
            'missing_features' => 'Missing features',
            'found_alternative' => 'Found alternative',
            'not_what_expected' => 'Not what I expected',
            'bugs_or_ux' => 'Bugs or poor UX',
            'personal_change' => 'Personal change',
            'other' => 'Other',
        ],
    ])

    <p>— The Fynla team</p>
@endsection

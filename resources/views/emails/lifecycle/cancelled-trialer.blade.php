@extends('emails.lifecycle._layout')
@section('title', 'Sorry to see you go')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>You cancelled your Fynla trial a few days ago — we're sorry it didn't work out. We're a small team trying to build the best UK financial planning tool we can, and the only way we get better is by hearing from people who decided it wasn't for them.</p>

    <p>If you have a moment, what was the main reason?</p>

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

    <p>Whichever you pick, you'll go to a one-question page where you can add anything else you'd like us to know. Or just close this email and we'll leave you alone — we won't ask again.</p>

    <p>— The Fynla team</p>
@endsection

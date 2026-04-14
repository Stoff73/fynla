@extends('emails.lifecycle._layout')
@section('title', 'Your Fynla payment didn\'t go through')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>We weren't able to process your latest Fynla subscription payment. This usually happens for one of a few reasons:</p>

    <ul>
        <li>The card on file expired</li>
        <li>The card has insufficient funds</li>
        <li>The bank flagged the transaction as suspicious</li>
        <li>The bank requires extra authentication</li>
    </ul>

    <p>The good news: your account is still active and your data is safe. We'll keep trying for a few more days, but if your payment isn't sorted{{ $gracePeriodEnd ? ' by ' . $gracePeriodEnd : '' }}, your subscription will lapse and you'll lose access.</p>

    @include('emails.lifecycle._button', ['url' => $updatePaymentUrl, 'label' => 'UPDATE PAYMENT METHOD'])

    <p>If something has changed and you'd like to talk to us, just click one of the buttons below — we won't hassle you either way:</p>

    @include('emails.lifecycle._quick-picks', [
        'buttons' => $feedbackUrls,
        'labels' => [
            'will_fix' => "I'll fix it shortly",
            'wants_to_cancel' => 'I want to cancel',
            'needs_help' => 'I need help',
        ],
    ])

    <p>— The Fynla team</p>
@endsection

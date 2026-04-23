@extends('emails.lifecycle._layout')
@section('title', 'Come back to Fynla')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>We noticed you signed up for Fynla a couple of weeks ago but didn't get a chance to look around. No worries — life gets busy.</p>

    <p>Your account is still here. We'd love for you to come back and see what Fynla can do for your financial planning.</p>

    <p>To make it easy, we're giving you a fresh 14-day trial — no payment details required, full access to everything Fynla offers:</p>

    <ul>
        <li>Track properties, pensions, savings, investments</li>
        <li>Plan for retirement and inheritance tax</li>
        <li>Get personalised recommendations from Fyn, our AI assistant</li>
        <li>See your complete financial picture in one place</li>
    </ul>

    @include('emails.lifecycle._button', ['url' => $magicUrl, 'label' => 'START MY 14-DAY TRIAL'])

    <p style="text-align: center; color: #717171; font-size: 13px;">This invitation expires in 7 days.</p>

    <p>— The Fynla team</p>
@endsection

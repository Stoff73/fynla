@extends('emails.layouts.master', [
    'title' => 'Confirm your subscription',
    'preheader' => 'One click to confirm your Fynla news subscription.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading' => 'Confirm your <span style="color:#f9a8c0;">subscription</span>',
        'subtitle' => 'One click and you\'re on the list.',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting' => 'Hi there,',
        'paragraphs' => [
            'Thanks for signing up to Fynla news. To finish your subscription, please confirm your email below.',
            'We\'ll only send you Fynla announcements and product updates &mdash; nothing else.',
        ],
    ])
    {{-- Rule 2 note: body + cta + notice + signoff form one continuous eggshell band
         (intentional — the email reads as a single "ask + reassure + sign off" unit).
         The notice's pink inner pill provides the visual break inside the band. --}}
    @include('emails.modules.cta', [
        'buttons' => [
            ['label' => 'Confirm subscription', 'url' => $confirmUrl, 'variant' => 'raspberry'],
        ],
    ])
    @include('emails.modules.notice', [
        'variant' => 'pink',
        'message' => 'Didn\'t ask for this? Just ignore this email &mdash; we won\'t add you to the list without confirmation.',
    ])
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

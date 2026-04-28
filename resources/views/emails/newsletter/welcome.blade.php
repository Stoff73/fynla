@extends('emails.layouts.master', [
    'title' => 'You\'re subscribed',
    'preheader' => 'You\'re on the Fynla news list — here\'s what to expect.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading' => 'You\'re <span style="color:#f9a8c0;">in</span>',
        'subtitle' => 'Here\'s what you\'ll receive from us.',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting' => 'Hi there,',
        'paragraphs' => [
            'Thanks for confirming. You\'re now on the Fynla news list and we\'ll keep you in the loop.',
        ],
    ])
    @include('emails.modules.bullet-list', [
        'heading' => 'What to expect',
        'items' => [
            'Product launches and major feature releases.',
            'Announcements from the Fynla team.',
            'Occasional deep-dives on UK personal finance topics.',
        ],
    ])
    {{-- Rule 2 note: body + bullet-list + cta + signoff form one continuous eggshell band
         (intentional — the email reads as a single "thanks + what to expect + read latest"
         unit). The bullet-list's #F7F6F4 inner box provides a visual break inside the band. --}}
    @include('emails.modules.cta', [
        'buttons' => [
            ['label' => 'Read latest news', 'url' => $newsUrl, 'variant' => 'raspberry'],
        ],
    ])
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', [
        'variant' => 'dark',
        'links' => [
            ['label' => 'Unsubscribe', 'url' => $unsubscribeUrl],
        ],
    ])
@endsection

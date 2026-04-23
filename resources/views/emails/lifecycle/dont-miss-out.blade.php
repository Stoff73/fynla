@extends('emails.layouts.master', [
    'title'     => "Don't miss out",
    'preheader' => "You're leaving value on the table.",
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Don\'t<br/><span style="color:#f9a8c0;">miss out</span>',
        'subtitle' => 'You\'re leaving value on the table.',
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 16px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Hi {{ $firstName }},</h2>
            <p style="margin:0;font-size:14px;color:#555;line-height:1.7;">
                We noticed that you haven't added your information yet. Fynla becomes powerful the moment you start filling it in &mdash; and every day you put it off is a day these savings stay out of reach.
            </p>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:12px 36px 4px;">
            <h3 style="margin:0;font-size:20px;font-weight:700;color:#1F2A44;">The value of adding your information</h3>
        </td>
    </tr>

    @include('emails.modules.stats-panel', [
        'panels' => [
            ['title' => 'Tax savings unlocked',   'number' => '&pound;3,500+', 'sentence' => 'The average Fynla user claws back over &pound;3,500 in tax each year just by completing their plan.'],
            ['title' => 'Retirement clarity',     'number' => '23 yrs',        'sentence' => 'See exactly how long your pots will last in retirement, once you link your pensions and investments.'],
            ['title' => 'Inheritance Tax saved',  'number' => '&pound;70,000+','sentence' => 'Typical households reduce their future IHT bill by tens of thousands with a completed plan.'],
        ],
    ])

    @include('emails.modules.discount-panels', [
        'progressPercent' => 0,
        'nextTierText'    => 'Next tier: 25% &rarr; 5% discount',
        'ctaLabel'        => 'Get started',
        'ctaUrl'          => $getStartedUrl,
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 14px;font-size:20px;font-weight:700;color:#1F2A44;">Next steps</h3>
            @foreach([
                ['title' => 'Add your income &amp; employment',          'description' => 'Five minutes to tell us what you earn and we\'ll show your take-home, tax bands, and relief headroom.'],
                ['title' => 'Link your pensions and investments',        'description' => 'We\'ll project your retirement income, spot Annual Allowance breaches and surface ISA headroom.'],
                ['title' => 'Complete your estate',                      'description' => 'Model your Inheritance Tax position and see a plan to reduce a 40% liability before it matters.'],
            ] as $i => $step)
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:{{ $i === 2 ? '28px' : '18px' }};">
                    <tr>
                        <td style="vertical-align:top;width:70px;padding-right:14px;">
                            <p style="margin:0;font-size:54px;font-weight:900;color:#e74c6f;line-height:0.9;text-align:center;font-family:'Segoe UI',Inter,Arial,sans-serif;">{{ $i + 1 }}</p>
                        </td>
                        <td style="vertical-align:top;">
                            <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1F2A44;line-height:1.2;">{!! $step['title'] !!}</p>
                            <p style="margin:0;font-size:13px;color:#1F2A44;line-height:1.6;">{!! $step['description'] !!}</p>
                        </td>
                    </tr>
                </table>
            @endforeach
            <div style="text-align:center;">
                <a href="{{ $addInformationUrl }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Add my information</a>
            </div>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

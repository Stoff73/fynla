@extends('emails.layouts.master', [
    'title'     => 'Why are you ' . trim($journeyPhrase, '?') . '?',
    'preheader' => 'The numbers that matter for your journey.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Why are you<br/><span style="color:#f9a8c0;">' . e($journeyPhrase) . '</span>',
        'subtitle' => 'The numbers that matter for your journey.',
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 16px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Hi {{ $firstName }},</h2>
            <p style="margin:0;font-size:14px;color:#555;line-height:1.7;">
                You told us you're focused on saving tax. Here's what Fynla sees in the numbers &mdash; and what finishing your plan unlocks.
            </p>
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 14px;font-size:20px;font-weight:700;color:#1F2A44;">Did you know?</h3>
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:middle;width:170px;padding-right:20px;text-align:center;">
                        <p style="margin:0;font-size:48px;font-weight:900;color:#1F2A44;line-height:0.95;letter-spacing:-1px;">&pound;3,500</p>
                        <p style="margin:6px 0 0;font-size:12px;font-weight:700;color:#9a1b4a;text-transform:uppercase;letter-spacing:1px;">Per year, on average</p>
                    </td>
                    <td style="vertical-align:middle;">
                        <p style="margin:0;font-size:14px;color:#1F2A44;line-height:1.6;">Higher-rate UK taxpayers leave &pound;3,500 on the table each year in unclaimed pension tax relief alone. Fynla flags your share the moment your plan is complete.</p>
                    </td>
                </tr>
            </table>
            <div style="text-align:center;margin-top:22px;">
                <a href="{{ $finishUrl }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Finish your dashboard</a>
            </div>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 8px;">
            <h3 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#1F2A44;">The value of completing your plan</h3>
            <p style="margin:0;font-size:14px;color:#555;line-height:1.6;">The three levers that do the heavy lifting for someone saving tax.</p>
        </td>
    </tr>

    @include('emails.modules.stats-panel', [
        'panels' => [
            ['title' => 'Tax-free pension lump sum', 'number' => '25%',           'sentence' => 'Of your pension pot can be taken tax-free from age 55 (rising to 57 from 2028).'],
            ['title' => 'Annual ISA allowance',      'number' => '&pound;20,000', 'sentence' => 'The average unused ISA headroom in the UK is &pound;8,400 &mdash; tax-free growth left on the table every year.'],
            ['title' => 'Annual gift exemption',     'number' => '&pound;3,000',  'sentence' => 'You can gift up to &pound;3,000 a year free of Inheritance Tax &mdash; unused allowance rolls one year forward.'],
        ],
    ])

    @include('emails.modules.discount-panels', [
        'progressPercent' => $progressPercent,
        'nextTierText'    => 'Next tier: 75% &rarr; 15% discount',
        'modules' => [
            ['title' => 'Protection', 'subtitle' => 'Life, income, critical illness', 'url' => $protectionUrl, 'status' => 'empty'],
            ['title' => 'Savings',    'subtitle' => 'Cash, ISAs, emergency fund',     'url' => $savingsUrl,    'status' => 'full'],
            ['title' => 'Investment', 'subtitle' => 'Portfolios & projections',       'url' => $investmentUrl, 'status' => 'full'],
            ['title' => 'Retirement', 'subtitle' => 'Pensions & decumulation',        'url' => $retirementUrl, 'status' => 'three-quarter'],
            ['title' => 'Estate',     'subtitle' => 'IHT, wills, LPAs',               'url' => $estateUrl,     'status' => 'empty'],
            ['title' => 'Goals',      'subtitle' => 'Life events & targets',          'url' => $goalsUrl,      'status' => 'empty'],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 14px;font-size:20px;font-weight:700;color:#1F2A44;">Next steps</h3>
            @foreach([
                ['title' => 'Check your ISA headroom',        'description' => 'We\'ll show how much of your &pound;20,000 allowance is still available before the tax year ends.'],
                ['title' => 'Claim your pension tax relief',  'description' => 'Fynla flags any higher-rate relief you haven\'t claimed via Self Assessment &mdash; often the biggest single win.'],
                ['title' => 'Start your estate plan',          'description' => 'Even a simple IHT plan can save your heirs tens of thousands &mdash; the earlier it\'s in place, the more flexible it is.'],
            ] as $i => $step)
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:{{ $i === 2 ? '28px' : '18px' }};">
                    <tr>
                        <td style="vertical-align:top;width:70px;padding-right:14px;">
                            <p style="margin:0;font-size:54px;font-weight:900;color:#e74c6f;line-height:0.9;text-align:center;font-family:'Segoe UI',Inter,Arial,sans-serif;">{{ $i + 1 }}</p>
                        </td>
                        <td style="vertical-align:top;">
                            <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1F2A44;line-height:1.2;">{{ $step['title'] }}</p>
                            <p style="margin:0;font-size:13px;color:#1F2A44;line-height:1.6;">{!! $step['description'] !!}</p>
                        </td>
                    </tr>
                </table>
            @endforeach
            <div style="text-align:center;">
                <a href="{{ $continueUrl }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Continue my plan</a>
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

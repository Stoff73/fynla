@extends('emails.layouts.master', [
    'title'     => "You're on your way",
    'preheader' => 'The more you add, the more you can save.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'You\'re on<br/><span style="color:#f9a8c0;">your way</span>',
        'subtitle' => 'The more you add, the more you can save.',
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 8px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Hi {{ $firstName }},</h2>
            <p style="margin:0 0 14px;font-size:14px;color:#555;line-height:1.7;">
                Every piece of data you add sharpens your plan and unlocks another layer of insight. Here are the three highest-value things you can do next &mdash; and what Fynla will tell you once you do.
            </p>
        </td>
    </tr>

    @include('emails.modules.numbered-steps', [
        'outerBg' => '#f5f0eb',
        'steps' => [
            ['title' => 'Add your pensions',     'description' => 'Project your retirement income, spot Annual Allowance breaches, and see if consolidating pots could save you in fees. <strong style="color:#22c55e;">Up to &pound;2,400/yr in tax relief claimed</strong>.'],
            ['title' => 'Add your investments',  'description' => 'Get a tax-efficiency read, ISA headroom alerts, and a diversification view across all providers. <strong style="color:#22c55e;">Up to &pound;1,600/yr in wrapper-tax leakage</strong>.'],
            ['title' => 'Add your estate',       'description' => 'Model your Inheritance Tax position and reduce a 40% liability without losing access to what you need. <strong style="color:#22c55e;">Up to &pound;70,000 in future IHT</strong>.'],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;text-align:center;">
            <p style="margin:0 0 14px;font-size:15px;font-weight:700;color:#1F2A44;line-height:1.5;">Ready to start? Pick up where you left off.</p>
            <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Go to my dashboard</a>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:24px 36px 8px;">
            <h3 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#1F2A44;">Unlock your discount</h3>
            <p style="margin:0 0 12px;font-size:14px;color:#555;line-height:1.6;">The more modules you complete, the bigger your discount when you subscribe.</p>
        </td>
    </tr>

    @include('emails.modules.discount-panels', [
        'progressPercent' => $progressPercent,
        'nextTierText'    => 'Next tier: 75% &rarr; 15% discount',
        'modules' => [
            ['title' => 'Protection', 'subtitle' => 'Life, income, critical illness', 'url' => $protectionUrl, 'status' => 'full'],
            ['title' => 'Savings',    'subtitle' => 'Cash, ISAs, emergency fund',     'url' => $savingsUrl,    'status' => 'three-quarter'],
            ['title' => 'Investment', 'subtitle' => 'Portfolios & projections',       'url' => $investmentUrl, 'status' => 'half'],
            ['title' => 'Retirement', 'subtitle' => 'Pensions & decumulation',        'url' => $retirementUrl, 'status' => 'quarter'],
            ['title' => 'Estate',     'subtitle' => 'IHT, wills, LPAs',               'url' => $estateUrl,     'status' => 'empty'],
            ['title' => 'Goals',      'subtitle' => 'Life events & targets',          'url' => $goalsUrl,      'status' => 'empty'],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;text-align:center;">
            <a href="{{ $fynUrl }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Get help from Fyn</a>
            <p style="margin:10px 0 0;font-size:12px;color:#1F2A44;">Fyn will guide you through each module &mdash; no exact details needed for now.</p>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

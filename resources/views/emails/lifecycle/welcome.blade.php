@extends('emails.layouts.master', [
    'title'     => 'Welcome to Fynla',
    'preheader' => "Welcome to Fynla, {$firstName} — your personalised plan is ready.",
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Welcome to<br/><span style="color:#f9a8c0;">Fynla</span>',
        'subtitle' => 'Your financial companion for life',
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 16px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Hi {{ $firstName }},</h2>
            <p style="margin:0;font-size:14px;color:#555;line-height:1.7;">
                Welcome to Fynla. We're glad to have you on board. Based on what you told us when you signed up, we've tailored the journey below to what matters most to you right now.
            </p>
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 10px;font-size:28px;font-weight:900;color:#1F2A44;line-height:1.2;">{{ $journeyHeadline }}</h3>
            <p style="margin:0 0 18px;font-size:14px;color:#1F2A44;line-height:1.6;">
                We'll prioritise pension allowances, ISA headroom and inheritance-tax planning. These account for the majority of the tax UK households routinely overpay.
            </p>
            <a href="{{ $journeyUrl }}" style="display:inline-block;padding:12px 28px;background:#e74c6f;color:#ffffff;font-size:14px;font-weight:700;border-radius:10px;text-decoration:none;">Start saving tax now</a>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:24px 36px 8px;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">Your account</h3>
            <div style="background:#FAD6E0;border-radius:10px;padding:16px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-family:'Segoe UI',Inter,Arial,sans-serif;">
                    <tr><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1F2A44;border-bottom:1px solid #e8e2db;">Email</td><td style="padding:8px 0;font-size:14px;color:#1F2A44;text-align:right;border-bottom:1px solid #e8e2db;">{{ $email }}</td></tr>
                    <tr><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1F2A44;border-bottom:1px solid #e8e2db;">Username</td><td style="padding:8px 0;font-size:14px;color:#1F2A44;text-align:right;border-bottom:1px solid #e8e2db;">{{ $username }}</td></tr>
                    <tr><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1F2A44;border-bottom:1px solid #e8e2db;">Plan</td><td style="padding:8px 0;font-size:14px;color:#1F2A44;text-align:right;border-bottom:1px solid #e8e2db;">{{ $planLabel }}</td></tr>
                    <tr><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1F2A44;">Start date</td><td style="padding:8px 0;font-size:14px;color:#1F2A44;text-align:right;">{{ $startDate }}</td></tr>
                </table>
            </div>
            <h3 style="margin:28px 0 6px;font-size:20px;font-weight:700;color:#1F2A44;">Tips for your journey</h3>
            <p style="margin:0 0 6px;font-size:14px;color:#555;line-height:1.6;">Insights that are specific to saving tax.</p>
        </td>
    </tr>

    @include('emails.modules.stats-panel', [
        'panels' => [
            ['title' => 'Pension annual allowance', 'number' => '&pound;60,000', 'sentence' => '1 in 3 higher-rate taxpayers fail to claim the full pension tax relief they\'re entitled to.'],
            ['title' => 'Annual ISA allowance',    'number' => '&pound;20,000', 'sentence' => 'The average unused ISA headroom in the UK is &pound;8,400 &mdash; tax-free growth left on the table every year.'],
            ['title' => 'Inheritance Tax rate',    'number' => '40%',           'sentence' => 'Without planning, 40% of every pound above the combined nil-rate bands (&pound;500,000 for most couples) is paid in tax.'],
        ],
    ])

    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 8px;">
            <h3 style="margin:0 0 14px;font-size:20px;font-weight:700;color:#1F2A44;">Plan your next steps</h3>
            @foreach([
                ['title' => 'Add your pensions',   'description' => 'So we can check you\'re using your Annual Allowance and reclaiming the tax relief you\'re owed.'],
                ['title' => 'Link your investments', 'description' => 'To surface ISA headroom you\'re not using and a tax-efficiency read across all your wrappers.'],
                ['title' => 'Log your estate',       'description' => 'Model an Inheritance-Tax-efficient plan before it matters — most households don\'t until it\'s too late.'],
            ] as $i => $step)
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:{{ $i === 2 ? '28px' : '18px' }};">
                    <tr>
                        <td style="vertical-align:top;width:70px;padding-right:14px;">
                            <p style="margin:0;font-size:54px;font-weight:900;color:#e74c6f;line-height:0.9;text-align:center;font-family:'Segoe UI',Inter,Arial,sans-serif;">{{ $i + 1 }}</p>
                        </td>
                        <td style="vertical-align:top;">
                            <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1F2A44;line-height:1.2;">{{ $step['title'] }}</p>
                            <p style="margin:0;font-size:13px;color:#555;line-height:1.6;">{{ $step['description'] }}</p>
                        </td>
                    </tr>
                </table>
            @endforeach
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;text-align:center;">
            <p style="margin:0 0 14px;font-size:15px;font-weight:700;color:#1F2A44;line-height:1.5;">Go to your dashboard now to get more recommendations</p>
            <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Take me to my dashboard</a>
        </td>
    </tr>
@endsection

@section('signoff')
    <tr>
        <td style="background:#f5f0eb;padding:24px 36px 20px;">
            <p style="margin:0 0 12px;font-size:14px;color:#555;line-height:1.7;">We're here to help you every step of the way on your journey to financial freedom.</p>
            <p style="margin:0 0 2px;font-size:14px;color:#6b7280;line-height:1.4;">Kindest regards,</p>
            <p style="margin:0;font-size:22px;font-weight:900;color:#1F2A44;line-height:1.2;letter-spacing:-0.3px;">The Fynla Team</p>
        </td>
    </tr>
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

@extends('emails.layouts.master', [
    'title'     => 'Well done — the maximum discount is yours',
    'preheader' => "You've earned the largest discount we offer.",
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Well done',
        'subtitle' => 'Congrats &mdash; you completed the whole journey.',
    ])
@endsection

@section('body')
    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:32px 36px;text-align:center;">
            <h2 style="margin:0 0 10px;font-size:24px;font-weight:900;color:#1F2A44;line-height:1.3;">Congrats {{ $firstName }}, you completed all of this journey!</h2>
            <p style="margin:0 0 20px;font-size:14px;color:#1F2A44;line-height:1.7;">You've built a plan most UK households don't have. That alone puts you in a different league &mdash; and now you've earned the largest discount we offer.</p>
            <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#9a1b4a;text-transform:uppercase;letter-spacing:1.5px;">Your discount</p>
            <p style="margin:0;font-size:80px;font-weight:900;color:#e74c6f;line-height:1;letter-spacing:-2px;">20%</p>
            <p style="margin:10px 0 22px;font-size:14px;color:#1F2A44;font-weight:700;">off your first year &mdash; applied automatically at checkout.</p>
            <a href="{{ $subscribeUrl }}" style="display:inline-block;padding:14px 44px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Claim my 20% and subscribe</a>
            <p style="margin:12px 0 0;font-size:12px;color:#1F2A44;">Cancel anytime. UK-based support. Data encrypted end-to-end.</p>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:28px 36px;">
            <h3 style="margin:0 0 14px;font-size:20px;font-weight:700;color:#1F2A44;">What to do next</h3>
            @foreach([
                ['title' => 'Review your plan',       'description' => 'Your holistic view is ready on the dashboard &mdash; it\'s the single page to share with anyone you trust with your money.'],
                ['title' => 'Invite your partner',    'description' => 'So Fynla can model joint positions and household-level tax savings single-user plans miss.'],
                ['title' => 'Schedule a check-in',    'description' => 'Life changes, and so should your plan &mdash; a quarterly review keeps everything current.'],
            ] as $i => $step)
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:{{ $i === 2 ? '0' : '18px' }};">
                    <tr>
                        <td style="vertical-align:top;width:70px;padding-right:14px;">
                            <p style="margin:0;font-size:54px;font-weight:900;color:#e74c6f;line-height:0.9;text-align:center;font-family:'Segoe UI',Inter,Arial,sans-serif;">{{ $i + 1 }}</p>
                        </td>
                        <td style="vertical-align:top;">
                            <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1F2A44;line-height:1.2;">{{ $step['title'] }}</p>
                            <p style="margin:0;font-size:13px;color:#555;line-height:1.6;">{!! $step['description'] !!}</p>
                        </td>
                    </tr>
                </table>
            @endforeach
        </td>
    </tr>

    <tr>
        <td style="padding:0;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#0F172A;">
                <tr>
                    <td colspan="2" style="padding:24px 36px 4px;">
                        <h3 style="margin:0;font-size:20px;font-weight:700;color:#ffffff;">Features you haven't explored yet</h3>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 12px 8px 36px;width:50%;vertical-align:top;">
                        <a href="{{ $scenarioUrl }}" style="display:block;background:#1F2A44;border-radius:12px;padding:16px;text-decoration:none;color:#ffffff;">
                            <p style="margin:0;font-size:17px;font-weight:700;color:#ffffff;line-height:1.2;">Scenario modelling</p>
                            <p style="margin:4px 0 0;font-size:11px;color:#94a3b8;">Test &quot;what if I retire at 60?&quot; in seconds.</p>
                        </a>
                    </td>
                    <td style="padding:20px 36px 8px 12px;width:50%;vertical-align:top;">
                        <a href="{{ $monteCarloUrl }}" style="display:block;background:#1F2A44;border-radius:12px;padding:16px;text-decoration:none;color:#ffffff;">
                            <p style="margin:0;font-size:17px;font-weight:700;color:#ffffff;line-height:1.2;">Monte Carlo</p>
                            <p style="margin:4px 0 0;font-size:11px;color:#94a3b8;">Probability ranges for your retirement pot.</p>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 12px 8px 36px;width:50%;vertical-align:top;">
                        <a href="{{ $willBuilderUrl }}" style="display:block;background:#1F2A44;border-radius:12px;padding:16px;text-decoration:none;color:#ffffff;">
                            <p style="margin:0;font-size:17px;font-weight:700;color:#ffffff;line-height:1.2;">Will builder</p>
                            <p style="margin:4px 0 0;font-size:11px;color:#94a3b8;">Get a solicitor-ready draft in 10 minutes.</p>
                        </a>
                    </td>
                    <td style="padding:8px 36px 8px 12px;width:50%;vertical-align:top;">
                        <a href="{{ $fynChatUrl }}" style="display:block;background:#1F2A44;border-radius:12px;padding:16px;text-decoration:none;color:#ffffff;">
                            <p style="margin:0;font-size:17px;font-weight:700;color:#ffffff;line-height:1.2;">Fyn chat</p>
                            <p style="margin:4px 0 0;font-size:11px;color:#94a3b8;">Ask anything about your plan, any time.</p>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:8px 36px 24px;text-align:center;">
                        <a href="{{ $checkFeaturesUrl }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Check out features</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

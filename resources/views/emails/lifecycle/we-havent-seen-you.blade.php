@extends('emails.layouts.master', [
    'title'     => "We haven't seen you",
    'preheader' => "It's been a while — your plan is still here.",
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'We haven\'t<br/><span style="color:#f9a8c0;">seen you</span>',
        'subtitle' => "It's been a while &mdash; here's what's waiting.",
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 8px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Hi {{ $firstName }},</h2>
            <p style="margin:0;font-size:14px;color:#555;line-height:1.7;">
                We've missed you. The good news: your plan is still here, and a few things have happened since you last signed in that are worth a quick look.
            </p>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:20px 36px 28px;">
            <h3 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#1F2A44;">Get more value from Fynla</h3>
            <p style="margin:0 0 18px;font-size:14px;color:#555;line-height:1.6;">Three things worth a few minutes of your time right now.</p>
            @foreach([
                ['title' => 'Refresh your plan',           'description' => 'ISA and pension allowances reset every April &mdash; a quick review keeps &pound;20,000 of tax-free growth on track.'],
                ['title' => 'Claim unclaimed tax relief', 'description' => 'Fynla flags any higher-rate pension relief you haven\'t claimed via Self Assessment &mdash; often thousands of pounds.'],
                ['title' => 'Run a what-if scenario',     'description' => 'Retire at 60? Buy a second home? Help your children? See the numbers before you commit.'],
            ] as $i => $step)
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:{{ $i === 2 ? '28px' : '18px' }};">
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
            <div style="text-align:center;">
                <a href="{{ $getMoreRecommendationsUrl }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Get more recommendations</a>
            </div>
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:32px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">You unlocked the maximum discount</h3>
            <p style="margin:0 0 22px;font-size:14px;color:#1F2A44;line-height:1.6;">It will be our little secret &mdash; we've held it for you.</p>
            <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#9a1b4a;text-transform:uppercase;letter-spacing:1.5px;">Your discount</p>
            <p style="margin:0;font-size:80px;font-weight:900;color:#e74c6f;line-height:1;letter-spacing:-2px;">20%</p>
            <p style="margin:10px 0 22px;font-size:14px;color:#1F2A44;font-weight:700;">off your first year &mdash; applied automatically at checkout.</p>
            <a href="{{ $subscribeUrl }}" style="display:inline-block;padding:14px 44px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Subscribe now</a>
            <p style="margin:12px 0 0;font-size:12px;color:#1F2A44;">Cancel anytime. UK-based support. Data encrypted end-to-end.</p>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

@extends('emails.layouts.master', [
    'title'     => 'Your Fynla account has been deleted',
    'preheader' => 'Your account is closed. You can restore it any time during the retention period.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Your account<br/>has been <span style="color:#f9a8c0;">deleted</span>',
        'subtitle' => 'You can still restore it during the retention period',
    ])
@endsection

@section('body')
    @php
        $retentionYears = config('retention.account_years', 7);
    @endphp

    @include('emails.modules.body', [
        'greeting'   => 'Hi ' . ($user->first_name ?? 'there') . ',',
        'paragraphs' => [
            'Your Fynla account has been closed as requested. You will no longer receive product emails, recommendations or reminders.',
            'Under Financial Conduct Authority rules we retain a limited record of your account for ' . $retentionYears . ' years from today. During that period you can restore your account simply by signing in &mdash; everything will be exactly as you left it.',
        ],
    ])

    <tr>
        <td style="background:#f5f0eb;padding:8px 36px 4px;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">Account closed</h3>
        </td>
    </tr>

    @include('emails.modules.summary-table', [
        'surroundBg' => '#f5f0eb',
        'rows' => [
            ['label' => 'Account email',    'value' => e($user->email ?? '')],
            ['label' => 'Closed on',        'value' => now()->format('j F Y')],
            ['label' => 'Retention period', 'value' => $retentionYears . ' years'],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:24px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;text-align:left;">Want it back?</h3>
            <p style="margin:0 0 18px;font-size:14px;color:#1F2A44;line-height:1.6;text-align:left;">Sign in with the email above any time during the retention period. Your data will be restored in full and you can pick a subscription to regain access to plans and recommendations.</p>
            <a href="https://fynla.org/login" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;font-family:'Segoe UI',Inter,Arial,sans-serif;">Restore your account</a>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

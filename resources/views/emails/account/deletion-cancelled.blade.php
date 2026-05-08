@extends('emails.layouts.master', [
    'title'     => 'Your scheduled deletion has been cancelled',
    'preheader' => 'Your Fynla account is active. Nothing has been changed or removed.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Your scheduled<br/>deletion is <span style="color:#f9a8c0;">cancelled</span>',
        'subtitle' => 'Your account stays active &mdash; nothing has changed',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting'   => 'Hi ' . ($user->first_name ?? 'there') . ',',
        'paragraphs' => [
            'Good news &mdash; the scheduled deletion of your Fynla account has been cancelled. Your account is active, your data is intact and you can keep using Fynla exactly as before.',
            'If you did not cancel this yourself, please sign in and review the recent activity on your account.',
        ],
    ])

    <tr>
        <td style="background:#f5f0eb;padding:8px 36px 4px;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">Account status</h3>
        </td>
    </tr>

    @include('emails.modules.summary-table', [
        'surroundBg' => '#f5f0eb',
        'rows' => [
            ['label' => 'Account email',    'value' => e($user->email ?? '')],
            ['label' => 'Status',           'value' => 'Active', 'valueColor' => '#22c55e'],
            ['label' => 'Cancelled on',     'value' => now()->format('j F Y')],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:24px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;text-align:left;">Pick up where you left off</h3>
            <p style="margin:0 0 18px;font-size:14px;color:#1F2A44;line-height:1.6;text-align:left;">Your dashboard is right where you left it &mdash; head back over and continue your plan.</p>
            <a href="https://fynla.org/dashboard" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;font-family:'Segoe UI',Inter,Arial,sans-serif;">Continue to dashboard</a>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

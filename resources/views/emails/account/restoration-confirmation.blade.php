@extends('emails.layouts.master', [
    'title'     => 'Welcome back to Fynla',
    'preheader' => 'Your account has been restored. All your data is back exactly as you left it.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Welcome back to<br/><span style="color:#f9a8c0;">Fynla</span>',
        'subtitle' => 'Your data is restored exactly as you left it',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting'   => 'Hi ' . ($user->first_name ?? 'there') . ',',
        'paragraphs' => [
            'Great to have you back. Your Fynla account has been restored and every record &mdash; goals, plans, accounts, conversations &mdash; is exactly as you left it.',
            'To regain full access to plans, recommendations and the AI assistant, choose a subscription. Restoration is free; only premium features need a plan.',
        ],
    ])

    <tr>
        <td style="background:#f5f0eb;padding:8px 36px 4px;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">Account restored</h3>
        </td>
    </tr>

    @include('emails.modules.summary-table', [
        'surroundBg' => '#f5f0eb',
        'rows' => [
            ['label' => 'Account email',    'value' => e($user->email ?? '')],
            ['label' => 'Status',           'value' => 'Active', 'valueColor' => '#22c55e'],
            ['label' => 'Restored on',      'value' => now()->format('j F Y')],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:24px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;text-align:left;">Pick up where you left off</h3>
            <p style="margin:0 0 18px;font-size:14px;color:#1F2A44;line-height:1.6;text-align:left;">Choose a plan to unlock recommendations, projections and Fyn, the in-app AI assistant. You can browse all available plans on the billing page.</p>
            <a href="https://fynla.org/billing/plans" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;font-family:'Segoe UI',Inter,Arial,sans-serif;">Choose a plan</a>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

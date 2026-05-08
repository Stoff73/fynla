@extends('emails.layouts.master', [
    'title'     => 'Final reminder: your Fynla account is deleted tomorrow',
    'preheader' => 'Last chance to cancel before deletion on ' . $executesAt->format('j F Y') . '.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Final reminder<br/><span style="color:#f9a8c0;">deletion tomorrow</span>',
        'subtitle' => 'This is your last chance to cancel',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting'   => 'Hi ' . ($user->first_name ?? 'there') . ',',
        'paragraphs' => [
            'Your Fynla account is scheduled for deletion tomorrow. After that, you will be signed out and your records move into the retention-only period required by the Financial Conduct Authority.',
        ],
    ])

    <tr>
        <td style="background:#f5f0eb;padding:8px 36px 4px;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">Deletion details</h3>
        </td>
    </tr>

    @include('emails.modules.summary-table', [
        'surroundBg' => '#f5f0eb',
        'rows' => [
            ['label' => 'Account email',    'value' => e($user->email ?? '')],
            ['label' => 'Scheduled for',    'value' => $executesAt->format('j F Y'), 'valueColor' => '#e74c6f'],
            ['label' => 'Time remaining',   'value' => 'Less than 24 hours'],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:24px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;text-align:left;">Last chance to keep your account</h3>
            <p style="margin:0 0 18px;font-size:14px;color:#1F2A44;line-height:1.6;text-align:left;">If you want to keep your Fynla account, you must cancel before the deletion runs &mdash; sign in now and head to <strong>Settings &rarr; Privacy</strong>.</p>
            <a href="https://fynla.org/settings/privacy" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;font-family:'Segoe UI',Inter,Arial,sans-serif;">Cancel deletion</a>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

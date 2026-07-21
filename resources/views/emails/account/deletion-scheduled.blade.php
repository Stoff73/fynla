@extends('emails.layouts.master', [
    'title'     => 'Your Fynla account is scheduled for deletion',
    'preheader' => 'Your account will be deleted on ' . $executesAt->format('j F Y') . '. Cancel any time.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Your account is <span style="color:#f9a8c0;">scheduled<br/>for deletion</span>',
        'subtitle' => 'You can cancel this any time before it happens',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting'   => 'Hi ' . ($user->first_name ?? 'there') . ',',
        'paragraphs' => [
            'You asked us to delete your Fynla account. We have scheduled this for the date below — you have until then to change your mind.',
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
            ['label' => 'Status',           'value' => 'Pending'],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:24px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;text-align:left;">What happens next</h3>
            <p style="margin:0 0 10px;font-size:14px;color:#1F2A44;line-height:1.6;text-align:left;">On the scheduled date, your account will be closed and you will be signed out of any active sessions. Your records are then retained for the period required by Financial Conduct Authority rules, after which they are permanently removed.</p>
            <p style="margin:0 0 18px;font-size:14px;color:#1F2A44;line-height:1.6;text-align:left;">If this was not what you intended, sign in and cancel the deletion in <strong>Settings &rarr; Privacy</strong>.</p>
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

@extends('emails.layouts.master', [
    'title'     => 'Clips awaiting approval — ' . $articleTitle,
    'preheader' => $clipCount . ' video clip' . ($clipCount === 1 ? '' : 's') . ' waiting for your approval. Auto-approves ' . $autoApproveMinutes . ' min before scheduled posting.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Clips awaiting<br/><span style="color:#f9a8c0;">your approval</span>',
        'subtitle' => 'Fynla Marketing Pipeline · Stage 3.5',
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 8px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Hi Marketing team,</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#555;line-height:1.7;">
                {{ $clipCount }} vertical (9:16) clip{{ $clipCount === 1 ? '' : 's' }} for
                <strong>{{ $articleTitle }}</strong> {{ $clipCount === 1 ? 'is' : 'are' }} ready.
                Review, approve or reject each. Anything still pending {{ $autoApproveMinutes }} min
                before its scheduled post time is auto-approved so we don't miss a slot.
            </p>
        </td>
    </tr>

    <tr>
        <td bgcolor="#EAF3DE" style="background:#EAF3DE;padding:20px 36px;">
            <p style="margin:0 0 12px;font-size:14px;color:#173404;font-weight:700;">Happy with all of them?</p>
            <a href="{{ $approveAllUrl }}" style="display:inline-block;padding:12px 28px;background:#3B6D11;color:#ffffff;font-size:14px;font-weight:700;border-radius:10px;text-decoration:none;">Approve all →</a>
        </td>
    </tr>

    @foreach($clips as $clip)
        <tr>
            <td style="background:#f5f0eb;padding:24px 36px 8px;">
                <div style="background:#ffffff;border:0.5px solid #d9d3cc;border-radius:12px;padding:16px 20px;">
                    <div style="font-size:11px;color:#7a7a7a;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 6px;">Clip {{ $clip['clip_index'] }}</div>

                    <p style="margin:0 0 6px;font-size:16px;font-weight:700;color:#1F2A44;">Scheduled to post</p>
                    <p style="margin:0 0 14px;font-size:15px;color:#1F2A44;">
                        <strong>{{ $clip['scheduled_day'] }}</strong>, {{ $clip['scheduled_date'] }} at
                        <strong>{{ $clip['scheduled_time'] }}</strong>
                    </p>

                    @if($clip['preview_url'])
                        <p style="margin:0 0 12px;font-size:13px;color:#555;">
                            <a href="{{ $clip['preview_url'] }}" style="color:#0C447C;text-decoration:none;font-weight:700;">▶ Preview / download clip</a>
                        </p>
                    @endif

                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 0;">
                        <tr>
                            <td style="padding-right:8px;">
                                <a href="{{ $clip['approve_url'] }}" style="display:inline-block;padding:8px 18px;background:#3B6D11;color:#ffffff;font-size:13px;font-weight:700;border-radius:8px;text-decoration:none;">Approve</a>
                            </td>
                            <td>
                                <a href="{{ $clip['reject_url'] }}" style="display:inline-block;padding:8px 18px;background:transparent;color:#A32D2D;border:1px solid #A32D2D;font-size:13px;font-weight:700;border-radius:8px;text-decoration:none;">Reject</a>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    @endforeach

    <tr>
        <td style="background:#f5f0eb;padding:16px 36px 24px;">
            <p style="margin:0 0 6px;font-size:13px;color:#555;line-height:1.6;">
                Prefer to review in one place? Open the
                <a href="{{ $approvalQueueUrl }}" style="color:#e74c6f;text-decoration:none;font-weight:700;">approval queue in the admin</a>.
            </p>
            <p style="margin:8px 0 0;font-size:11px;color:#7a7a7a;line-height:1.5;">
                Article slug: <code style="background:#e8e2db;padding:1px 4px;border-radius:3px;">{{ $articleSlug }}</code><br/>
                Auto-approval kicks in {{ $autoApproveMinutes }} min before each clip's scheduled post time. Magic links expire in 48 hours.
            </p>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff', [
        'signoffText' => 'The Fynla Marketing Pipeline',
    ])
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection

@extends('emails.layouts.master', [
    'title'     => 'Posts ready to review — ' . $articleTitle,
    'preheader' => $postCount . ' post' . ($postCount === 1 ? '' : 's') . ' waiting for your approval — ' . $articleTitle,
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Posts ready<br/><span style="color:#f9a8c0;">for your approval</span>',
        'subtitle' => 'Fynla Marketing Pipeline · Stage 4',
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 8px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Hi Marketing team,</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#555;line-height:1.7;">
                {{ $postCount }} social post{{ $postCount === 1 ? '' : 's' }} {{ $postCount === 1 ? 'is' : 'are' }} waiting
                for you on the approval queue — two caption variants (A / B) × Instagram, Facebook, TikTok, per generated clip
                of <strong>{{ $articleTitle }}</strong>.
                Approve, edit, reject, or swap the destination to a campaign landing page before we schedule.
            </p>
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 10px;font-size:22px;font-weight:900;color:#1F2A44;line-height:1.2;">Open the approval queue</h3>
            <p style="margin:0 0 18px;font-size:14px;color:#1F2A44;line-height:1.6;">
                Direct link filtered to this article. Log in as an admin if prompted.
            </p>
            <a href="{{ $approvalUrl }}" style="display:inline-block;padding:12px 28px;background:#e74c6f;color:#ffffff;font-size:14px;font-weight:700;border-radius:10px;text-decoration:none;">Review posts</a>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:24px 36px 8px;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">What to check</h3>
            <div style="background:#FAD6E0;border-radius:10px;padding:16px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-family:'Segoe UI',Inter,Arial,sans-serif;">
                    <tr><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1F2A44;border-bottom:1px solid #e8e2db;">1.</td><td style="padding:8px 0;font-size:14px;color:#1F2A44;border-bottom:1px solid #e8e2db;">Read caption A + B side by side. Tone check.</td></tr>
                    <tr><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1F2A44;border-bottom:1px solid #e8e2db;">2.</td><td style="padding:8px 0;font-size:14px;color:#1F2A44;border-bottom:1px solid #e8e2db;">Confirm the hashtag mix (2–5, relevant, non-generic).</td></tr>
                    <tr><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1F2A44;border-bottom:1px solid #e8e2db;">3.</td><td style="padding:8px 0;font-size:14px;color:#1F2A44;border-bottom:1px solid #e8e2db;">Change destination if the article isn't the right landing page — pick a campaign or paste a custom URL.</td></tr>
                    <tr><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1F2A44;">4.</td><td style="padding:8px 0;font-size:14px;color:#1F2A44;">Approve. The pipeline schedules to Buffer at the next optimum slot per platform.</td></tr>
                </table>
            </div>

            <p style="margin:16px 0 0;font-size:12px;color:#7a7a7a;line-height:1.5;">
                Article slug: <code style="background:#e8e2db;padding:1px 4px;border-radius:3px;">{{ $articleSlug }}</code>
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

@extends('emails.layouts.master', [
    'title'     => 'Weekly social report — ' . $week_start . ' to ' . $week_end,
    'preheader' => $post_count . ' post(s), ' . $total_sessions . ' GA session(s) this week',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Weekly<br/><span style="color:#f9a8c0;">social report</span>',
        'subtitle' => $week_start . ' to ' . $week_end,
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 8px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">This week at a glance</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#555;line-height:1.7;">
                <strong>{{ $post_count }}</strong> post{{ $post_count === 1 ? '' : 's' }} scheduled or published,
                driving <strong>{{ $total_sessions }}</strong> GA session{{ $total_sessions === 1 ? '' : 's' }}
                to fynla.org from social.
            </p>

            @if(!$ga_available)
                <p style="margin:0 0 12px;padding:10px;background:#FAD6E0;border-radius:8px;font-size:13px;color:#1F2A44;">
                    ⚠ GA data unavailable this week — traffic totals may be missing.
                </p>
            @endif
            @if(!$buffer_available)
                <p style="margin:0 0 12px;padding:10px;background:#FAD6E0;border-radius:8px;font-size:13px;color:#1F2A44;">
                    ⚠ Buffer metrics unavailable — engagement counts may be missing.
                </p>
            @endif
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 14px;font-size:20px;font-weight:900;color:#1F2A44;">Per platform</h3>
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-family:'Segoe UI',Inter,Arial,sans-serif;font-size:13px;background:#ffffff;border-radius:10px;overflow:hidden;">
                <thead>
                    <tr style="background:#f5f0eb;">
                        <th align="left" style="padding:10px 12px;color:#1F2A44;">Platform</th>
                        <th align="right" style="padding:10px 12px;color:#1F2A44;">Posts</th>
                        <th align="right" style="padding:10px 12px;color:#1F2A44;">Reach</th>
                        <th align="right" style="padding:10px 12px;color:#1F2A44;">Likes</th>
                        <th align="right" style="padding:10px 12px;color:#1F2A44;">Comments</th>
                        <th align="right" style="padding:10px 12px;color:#1F2A44;">Shares</th>
                        <th align="right" style="padding:10px 12px;color:#1F2A44;">Sessions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(['instagram', 'facebook', 'tiktok'] as $platform)
                        <tr style="border-top:1px solid #f0eae4;">
                            <td style="padding:10px 12px;color:#1F2A44;text-transform:capitalize;font-weight:700;">{{ $platform }}</td>
                            <td align="right" style="padding:10px 12px;color:#1F2A44;">{{ $by_platform[$platform]['count'] ?? 0 }}</td>
                            <td align="right" style="padding:10px 12px;color:#1F2A44;">{{ $by_platform[$platform]['reach'] ?? 0 }}</td>
                            <td align="right" style="padding:10px 12px;color:#1F2A44;">{{ $by_platform[$platform]['likes'] ?? 0 }}</td>
                            <td align="right" style="padding:10px 12px;color:#1F2A44;">{{ $by_platform[$platform]['comments'] ?? 0 }}</td>
                            <td align="right" style="padding:10px 12px;color:#1F2A44;">{{ $by_platform[$platform]['shares'] ?? 0 }}</td>
                            <td align="right" style="padding:10px 12px;color:#1F2A44;">{{ $sessions_by_platform[$platform]['sessions'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:24px 36px;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">A / B verdict</h3>
            @if($winning_variant)
                <p style="margin:0 0 8px;font-size:14px;color:#1F2A44;">
                    <strong>Variant {{ $winning_variant }}</strong> outperformed this week
                    (weighted engagement score: {{ $variant_scores[$winning_variant] }}).
                </p>
                <p style="margin:0;font-size:13px;color:#555;">
                    Scores are: 1×likes + 2×comments + 3×shares across all posts of the variant.
                </p>
            @else
                <p style="margin:0;font-size:14px;color:#555;">No engagement data this week — no verdict.</p>
            @endif
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

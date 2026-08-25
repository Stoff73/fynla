@extends('emails.layouts.master', [
    'title'     => 'Quarterly video audit',
    'preheader' => $fileCount . ' file(s) over ' . $olderThanDays . ' days old — please decide what to delete',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Quarterly<br/><span style="color:#f9a8c0;">video audit</span>',
        'subtitle' => 'Housekeeping — nothing gets deleted without your say-so',
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:28px 36px 8px;">
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Hi Marketing team,</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#555;line-height:1.7;">
                The Fynla server holds <strong>{{ $fileCount }} social video file{{ $fileCount === 1 ? '' : 's' }}</strong>
                over {{ $olderThanDays }} days old ({{ $totalMb }} MB total). None of these will be deleted automatically.
                Reply to this email listing any relative paths you'd like removed, or reply "keep all" if everything should stay.
            </p>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:8px 36px 24px;">
            <div style="background:#ffffff;border-radius:10px;padding:16px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-family:'Segoe UI',Inter,Arial,sans-serif;font-size:13px;">
                    <thead>
                        <tr>
                            <th align="left" style="padding:8px 0;color:#1F2A44;border-bottom:2px solid #e8e2db;">File</th>
                            <th align="right" style="padding:8px 0;color:#1F2A44;border-bottom:2px solid #e8e2db;">Age (days)</th>
                            <th align="right" style="padding:8px 0;color:#1F2A44;border-bottom:2px solid #e8e2db;">Size (MB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td style="padding:6px 0;color:#1F2A44;border-bottom:1px solid #f0eae4;font-family:monospace;font-size:12px;">{{ $row['relative'] }}</td>
                                <td align="right" style="padding:6px 0;color:#555;border-bottom:1px solid #f0eae4;">{{ $row['age_days'] ?? '—' }}</td>
                                <td align="right" style="padding:6px 0;color:#555;border-bottom:1px solid #f0eae4;">{{ $row['size_mb'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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

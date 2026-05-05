{{--
  Stats Panel — alternating two-column stat rows. Left side horizon blue (#1F2A44)
  with a title + large number. Right side solid light blue (#dbeafe) with a
  supporting sentence. Successive panels alternate sides automatically so the
  dark and light cells form a visual zig-zag down the email (avoids Rule 2
  same-colour strips).

  Variables:
    $panels  array<array{title:string,number:string,sentence:string}>
             Rendered top to bottom. First panel is horizon-left;
             every subsequent panel swaps sides.

  Notes:
    - Each panel is one <tr> containing a nested 2-column table. The outer <tr>
      has no background — the cells ARE the backgrounds.
    - Numbers render in 40px/900 Segoe UI so currency amounts and percentages
      read at a glance on mobile. Escape HTML-significant chars in $number
      (e.g. "&pound;60,000", "40%", "1 in 3").
--}}
@php
    $panels = $panels ?? [];
@endphp
@foreach($panels as $i => $panel)
    @php
        $title    = $panel['title']    ?? '';
        $number   = $panel['number']   ?? '';
        $sentence = $panel['sentence'] ?? '';
        $darkLeft = ($i % 2 === 0);
    @endphp
    <tr>
        <td style="padding: 0;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
                <tr>
                    @if($darkLeft)
                        <td width="50%" bgcolor="#1F2A44" style="background: #1F2A44; padding: 24px 28px; vertical-align: middle;">
                            <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #f9a8c0; line-height: 1.3; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $title }}</p>
                            <p style="margin: 0; font-size: 40px; font-weight: 900; color: #ffffff; line-height: 1; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $number !!}</p>
                        </td>
                        <td width="50%" bgcolor="#dbeafe" style="background: #dbeafe; padding: 24px 28px; vertical-align: middle;">
                            <p style="margin: 0; font-size: 14px; color: #1F2A44; line-height: 1.6; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $sentence !!}</p>
                        </td>
                    @else
                        <td width="50%" bgcolor="#dbeafe" style="background: #dbeafe; padding: 24px 28px; vertical-align: middle;">
                            <p style="margin: 0; font-size: 14px; color: #1F2A44; line-height: 1.6; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $sentence !!}</p>
                        </td>
                        <td width="50%" bgcolor="#1F2A44" style="background: #1F2A44; padding: 24px 28px; vertical-align: middle;">
                            <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #f9a8c0; line-height: 1.3; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $title }}</p>
                            <p style="margin: 0; font-size: 40px; font-weight: 900; color: #ffffff; line-height: 1; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $number !!}</p>
                        </td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
@endforeach

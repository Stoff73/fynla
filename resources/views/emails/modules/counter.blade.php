{{--
  Counter / Urgency Display — large raspberry number with caption.

  Variables:
    $number     string|int   e.g. 3
    $label      string       e.g. "Days remaining"
    $subtext    string|null  e.g. "Your Premium access ends on 18 April 2026"
    $outerBg    string       Outer <td> bg. Default #ffffff.
--}}
@php
    $number  = $number  ?? '0';
    $label   = $label   ?? '';
    $subtext = $subtext ?? null;
    $outerBg = $outerBg ?? '#f5f0eb';
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 16px 36px; text-align: center; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
        <p style="margin: 0; font-size: 96px; font-weight: 900; color: #e74c6f; line-height: 1; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $number }}</p>
        <p style="margin: 4px 0 0 0; font-size: 18px; font-weight: 700; color: #1F2A44; text-transform: uppercase; letter-spacing: 1px; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $label }}</p>
        @if($subtext)
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #888; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $subtext }}</p>
        @endif
    </td>
</tr>

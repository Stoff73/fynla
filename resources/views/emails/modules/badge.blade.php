{{--
  Inline Badge / Label — small chip used for reference codes (e.g. invoice number).

  Variables:
    $text     string  Badge text.
    $outerBg  string  Outer <td> bg. Default #ffffff.
--}}
@php
    $text    = $text    ?? '';
    $outerBg = $outerBg ?? '#f5f0eb';
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 16px 36px;">
        <span style="background: #F7F6F4; color: #1F2A44; font-size: 13px; font-weight: 700; padding: 4px 12px; border-radius: 4px; letter-spacing: 0.5px; display: inline-block; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $text }}</span>
    </td>
</tr>

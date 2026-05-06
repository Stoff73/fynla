{{--
  Description box — pre-formatted user-supplied text (e.g. bug reports).
  Preserves whitespace and long-word wrapping.

  Variables:
    $text     string  The description text. Rendered escaped.
    $outerBg  string  Outer <td> bg. Default #ffffff.
--}}
@php
    $text    = $text    ?? '';
    $outerBg = $outerBg ?? '#f5f0eb';
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 16px 36px;">
        <div style="background: #FAD6E0; border-radius: 6px; padding: 15px; white-space: pre-wrap; word-break: break-word; font-size: 13px; color: #555; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $text }}</div>
    </td>
</tr>

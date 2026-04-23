{{--
  Console / code box — dark monospace block for stack traces or code output.

  Variables:
    $text       string  The code/console content. Rendered escaped.
    $outerBg    string  Outer <td> bg. Default #ffffff.
    $maxHeight  string  Optional max-height CSS. Default '120px'.
--}}
@php
    $text      = $text      ?? '';
    $outerBg   = $outerBg   ?? '#f5f0eb';
    $maxHeight = $maxHeight ?? '120px';
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 16px 36px;">
        <div style="background: #1f2937; border-radius: 6px; padding: 15px; font-family: 'Courier New', monospace; font-size: 12px; color: #f9fafb; white-space: pre-wrap; max-height: {{ $maxHeight }}; overflow-y: auto;">{{ $text }}</div>
    </td>
</tr>

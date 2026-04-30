{{--
  Body text block — greeting (optional h2) + paragraphs, on white bg by default.
  Section bg: configurable via $bg. Default #ffffff. Allowed: #ffffff, #f5f0eb (eggshell).

  Variables:
    $greeting   string|null  e.g. "Hi James,". Omit to skip.
    $paragraphs string[]     Array of paragraph strings. HTML allowed — caller escapes.
    $bg         string       Background color hex. Default #ffffff.
    $padding    string       CSS padding. Default "28px 36px".

  Note: Rule 4 does NOT apply here — this is not a summary/detail table.
--}}
@php
    $greeting   = $greeting   ?? null;
    $paragraphs = $paragraphs ?? [];
    $bg         = $bg         ?? '#f5f0eb';
    $padding    = $padding    ?? '28px 36px';
@endphp
<tr>
    <td style="background: {{ $bg }}; padding: {{ $padding }}; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
        @if($greeting)
            <h2 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 700; color: #1F2A44; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $greeting }}</h2>
        @endif
        @foreach($paragraphs as $paragraph)
            <p style="margin: 0 0 14px 0; font-size: 14px; color: #555; line-height: 1.7; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $paragraph !!}</p>
        @endforeach
    </td>
</tr>

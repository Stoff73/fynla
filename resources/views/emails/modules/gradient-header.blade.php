{{--
  Simple Gradient Header — no character (Rule 1: gradient is fixed, do not change).
  Section bg: linear-gradient(135deg, #1F2A44, #2d3a5c) over fallback #1F2A44.

  Variables:
    $heading    string       Heading text.
    $subtitle   string|null  Optional subtitle under the heading.
--}}
@php
    $heading  = $heading  ?? 'Fynla';
    $subtitle = $subtitle ?? null;
@endphp
<tr>
    <td bgcolor="#1F2A44" style="background-color: #1F2A44; background-image: linear-gradient(135deg, #1F2A44 0%, #2d3a5c 100%); padding: 24px 36px;">
        <h2 style="margin: 0; font-size: 22px; font-weight: 700; color: #ffffff; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $heading }}</h2>
        @if($subtitle)
            <p style="margin: 4px 0 0 0; font-size: 13px; color: rgba(255,255,255,0.7); font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $subtitle }}</p>
        @endif
    </td>
</tr>

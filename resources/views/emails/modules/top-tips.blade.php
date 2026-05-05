{{--
  Top Tips Panel — three full-width rounded white cards on a coloured section
  background. All three cards share the same min-height so they render at the
  same height in desktop (side-by-side) AND mobile (stacked) views.

  Layout:
    - Full-width table, 3 columns × 33.33% each.
    - 8px horizontal gutter between cards via border-spacing:8px 0.
    - Each card: white #ffffff background, 12px border-radius, 16px padding.
    - Equal-height: outer <td> and inner <div> both carry height/min-height
      (the <td> height attribute is the one Outlook/Gmail respect; box-sizing
      keeps padding inside on browsers/modern clients).

  Variables:
    $tips     array<array{title:string,description:string}>  Exactly 3 tips.
    $heading  string   Section H3. Default "Top tips".
    $outerBg  string   Outer <td> bg. Default '#fce4ec' (light pink).
    $height   int      Uniform card height in px. Default 150.
    $padding  string   Outer <td> padding. Default "28px 36px".
--}}
@php
    $tips    = $tips    ?? [];
    $heading = $heading ?? 'Top tips';
    $outerBg = $outerBg ?? '#fce4ec';
    $height  = $height  ?? 150;
    $padding = $padding ?? '28px 36px';
    $innerHeight = max(60, $height - 32); // card height minus top+bottom padding (16+16)
    // Pad to exactly 3 tips so the grid always balances.
    $tips = array_slice(array_pad($tips, 3, ['title' => '', 'description' => '']), 0, 3);
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: {{ $padding }}; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
        @if($heading)
            <h3 style="margin: 0 0 14px 0; font-size: 20px; font-weight: 700; color: #1F2A44; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $heading }}</h3>
        @endif
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate; border-spacing: 8px 0;">
            <tr>
                @foreach($tips as $tip)
                    <td width="33.33%" height="{{ $height }}" style="vertical-align: top; height: {{ $height }}px;">
                        <div style="background: #ffffff; border-radius: 12px; padding: 16px; min-height: {{ $innerHeight }}px; box-sizing: border-box;">
                            <p style="margin: 0 0 6px 0; font-size: 14px; font-weight: 700; color: #1F2A44; line-height: 1.3; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $tip['title'] ?? '' }}</p>
                            <p style="margin: 0; font-size: 12px; color: #555; line-height: 1.5; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $tip['description'] ?? '' !!}</p>
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </td>
</tr>

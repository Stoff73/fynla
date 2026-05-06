{{--
  Numbered Steps — large raspberry number on the left, top-aligned title +
  supporting sentence on the right. Used for onboarding "Plan your next steps"
  and "Get started" action lists.

  Layout details:
    - Number: 54px / weight 900 / #e74c6f / line-height 0.9 so its optical top
      aligns with the title text.
    - Title: 15px / weight 700 / horizon blue #1F2A44 / line-height 1.2.
    - Description: 13px / #555 / line-height 1.6.
    - Number cell is 70px wide with 14px right padding.
    - Both cells use vertical-align: top.
    - Rows 1..n-1 have margin-bottom: 18px. Final row has margin-bottom: 28px
      so it breathes before whatever CTA/section follows.

  Variables:
    $steps    array<array{title:string,description:string}>
    $outerBg  string  Outer <td> bg. Default '#ffffff'.
    $padding  string  Outer <td> padding. Default '8px 36px 0'.
--}}
@php
    $steps   = $steps   ?? [];
    $outerBg = $outerBg ?? '#f5f0eb';
    $padding = $padding ?? '8px 36px 0';
    $total   = count($steps);
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: {{ $padding }}; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
        @foreach($steps as $i => $step)
            @php
                $isLast  = ($i === $total - 1);
                $marginB = $isLast ? '28px' : '18px';
            @endphp
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: {{ $marginB }};">
                <tr>
                    <td style="vertical-align: top; width: 70px; padding-right: 14px;">
                        <p style="margin: 0; font-size: 54px; font-weight: 900; color: #e74c6f; line-height: 0.9; text-align: center; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $i + 1 }}</p>
                    </td>
                    <td style="vertical-align: top;">
                        <p style="margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: #1F2A44; line-height: 1.2; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $step['title'] ?? '' }}</p>
                        <p style="margin: 0; font-size: 13px; color: #555; line-height: 1.6; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $step['description'] ?? '' !!}</p>
                    </td>
                </tr>
            </table>
        @endforeach
    </td>
</tr>

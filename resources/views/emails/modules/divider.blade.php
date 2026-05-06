{{--
  Horizontal divider — useful for separating two sections that would otherwise
  share the same background colour (Rule 2).

  Variables:
    $outerBg  string  Outer <td> bg. Default #ffffff.
--}}
@php
    $outerBg = $outerBg ?? '#f5f0eb';
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 8px 36px;">
        <hr style="border: none; border-top: 1px solid #e8e2db; margin: 12px 0 0 0;" />
    </td>
</tr>

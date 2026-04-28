{{--
  Bullet List in an eggshell rounded box.

  Variables:
    $heading  string|null  Optional bold line above the list.
    $items    string[]     List items (HTML allowed).
    $outerBg  string       Outer <td> bg. Default #ffffff.
--}}
@php
    $heading = $heading ?? null;
    $items   = $items   ?? [];
    $outerBg = $outerBg ?? '#f5f0eb';
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 16px 36px; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
        @if($heading)
            <p style="margin: 0 0 8px 0; font-size: 14px; font-weight: 700; color: #1F2A44; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $heading }}</p>
        @endif
        <ul style="margin: 0; padding: 16px 20px 16px 40px; font-size: 14px; color: #555; line-height: 2; background: #F7F6F4; border-radius: 10px; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
            @foreach($items as $item)
                <li>{!! $item !!}</li>
            @endforeach
        </ul>
    </td>
</tr>

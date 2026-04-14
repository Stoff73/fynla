<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 20px auto;">
    @foreach ($buttons as $reason => $url)
        <tr>
            <td style="padding: 4px 0;">
                <a href="{{ $url }}" style="display: inline-block; padding: 10px 20px; background-color: #FFFFFF; color: #1F2A44; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid #1F2A44; border-radius: 6px; min-width: 200px; text-align: center;">{{ $labels[$reason] ?? $reason }}</a>
            </td>
        </tr>
    @endforeach
</table>

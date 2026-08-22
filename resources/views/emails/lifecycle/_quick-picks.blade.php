<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 20px auto;">
    @foreach ($buttons as $reason => $url)
        <tr>
            <td style="padding: 4px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto; border-collapse: separate;"><tr><td align="center" bgcolor="#FFFFFF" style="padding: 10px 20px; background-color: #FFFFFF; border: 1px solid #1F2A44; border-radius: 6px;"><a href="{{ $url }}" style="color: #1F2A44; text-decoration: none; font-weight: 600; font-size: 14px; min-width: 200px; text-align: center;">{{ $labels[$reason] ?? $reason }}</a></td></tr></table>
            </td>
        </tr>
    @endforeach
</table>

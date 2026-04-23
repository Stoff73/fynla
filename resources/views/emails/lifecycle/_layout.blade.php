<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Fynla')</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1F2A44; background-color: #F7F6F4; margin: 0; padding: 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F7F6F4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background-color: #FFFFFF; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
                    <tr>
                        <td style="padding: 30px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F7F6F4; padding: 20px 30px; text-align: center; font-size: 13px; color: #717171; border-top: 1px solid #EEEEEE;">
                            <p style="margin: 5px 0;">&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
                            <p style="margin: 5px 0;">You're receiving this because you signed up for Fynla.</p>
                            <p style="margin: 5px 0;">You can manage which emails you receive in your <a href="{{ config('app.url') }}/profile/notifications" style="color: #E83E6D; text-decoration: none;">account settings</a>.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

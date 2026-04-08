<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Issue</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .header { background: linear-gradient(135deg, #1F2A44 0%, #2d3a5c 100%); padding: 24px 30px; text-align: center; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0; font-weight: 700; }
        .content { padding: 30px; }
        .content p { margin: 0 0 15px 0; }
        .warning-box { background: #fdf2f8; border-left: 4px solid #E83E6D; padding: 12px 16px; margin: 20px 0; font-size: 14px; border-radius: 0 4px 4px 0; }
        .info-box { background: #f0f4ff; border-left: 4px solid #5854E6; padding: 12px 16px; margin: 20px 0; font-size: 13px; border-radius: 0 4px 4px 0; }
        .btn { display: inline-block; background: #E83E6D; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin: 16px 0; }
        .footer { padding: 20px 30px; background: #fafafa; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Issue</h1>
        </div>

        <div class="content">
            <p>Hello {{ $user->first_name ?? 'there' }},</p>

            <div class="warning-box">
                We were unable to process your automatic renewal payment of <strong>&pound;{{ $amount }}</strong> for your <strong>{{ $planName }}</strong> plan.
            </div>

            <p>Don't worry — we will automatically retry the payment. If the issue persists, you may need to update your payment method.</p>

            @if($periodEnd)
            <div class="info-box">
                Your current access continues until <strong>{{ $periodEnd }}</strong>. Please ensure your payment method is up to date before then to avoid any interruption to your service.
            </div>
            @endif

            <p style="text-align: center;">
                <a href="{{ config('app.url') }}/profile" class="btn">Update Payment Method</a>
            </p>

            <p style="font-size: 13px; color: #717171;">If you believe this is an error or need assistance, please contact us at <a href="mailto:support@fynla.org" style="color: #E83E6D;">support@fynla.org</a>.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

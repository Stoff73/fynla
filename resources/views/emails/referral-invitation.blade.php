<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've Been Invited to Fynla</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .header { background: linear-gradient(135deg, #1F2A44 0%, #2d3a5c 100%); padding: 24px 30px; text-align: center; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0; font-weight: 700; }
        .header p { color: rgba(255,255,255,0.7); font-size: 13px; margin: 4px 0 0; }
        .content { padding: 30px; }
        .content p { margin: 0 0 15px 0; }
        .highlight-box { background: #f0fdf7; border-left: 4px solid #20B486; padding: 14px 18px; margin: 20px 0; font-size: 14px; border-radius: 0 6px 6px 0; }
        .btn { display: inline-block; background: #E83E6D; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-weight: 600; font-size: 16px; margin: 20px 0; }
        .btn-container { text-align: center; margin: 25px 0; }
        .footer { padding: 20px 30px; background: #fafafa; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
        .footer a { color: #E83E6D; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>You've Been Invited</h1>
            <p>Your friend thinks you'd enjoy Fynla</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <p><strong>{{ $referrerName }}</strong> thinks you'd like Fynla — your personal financial planning companion.</p>

            <p>Fynla helps you plan your savings, investments, retirement, and estate with confidence, all within UK regulations. Whether you're just starting out or planning ahead, Fynla gives you the tools to take control of your financial future.</p>

            <div class="highlight-box">
                <strong>Bonus:</strong> Sign up and you'll both get extra time on your subscriptions — an extra week with a monthly plan, or an extra month with an annual plan.
            </div>

            <div class="btn-container">
                <a href="{{ $registerUrl }}" class="btn">Create Your Free Account</a>
            </div>

            <p style="font-size: 13px; color: #717171;">Your referral code <strong>{{ $referralCode }}</strong> will be applied automatically when you register using the link above.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
            <p>Questions? Contact us at <a href="mailto:support@fynla.org">support@fynla.org</a></p>
        </div>
    </div>
</body>
</html>

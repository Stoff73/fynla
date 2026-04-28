{{--
  Fynla Master Email Layout.

  Variables:
    $title       string  <title> tag text.
    $preheader   string  Optional hidden preview line shown in inbox previews.

  Slots (all optional, rendered in order):
    @section('logoBar')       @include('emails.modules.logo-bar')
    @section('header')        @include('emails.modules.hero-header') or gradient-header
    @section('body')          Body sections — assemble from emails.modules.*
    @section('signoff')       @include('emails.modules.signoff')
    @section('footer')        @include('emails.modules.footer', ['variant' => 'dark'])

  The outer page background is white (#ffffff). The 600px container bg is
  eggshell (#f5f0eb). Adjacent <tr> sections inside the container must not
  share the same solid background unless they render as one continuous band —
  see .claude/skills/email-template/SKILL.md (Rules 2 and 7).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title ?? 'Fynla' }}</title>
</head>
<body style="font-family: 'Segoe UI', Inter, Arial, sans-serif; margin: 0; padding: 0; background-color: #ffffff; color: #1F2A44;">
    @if(!empty($preheader))
        <div style="display:none !important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;max-height:0;max-width:0;">{{ $preheader }}</div>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background: #f5f0eb; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px #d9d3cc; font-family: 'Segoe UI', Inter, Arial, sans-serif;">

                    @yield('logoBar')
                    @yield('header')
                    @yield('body')
                    @yield('signoff')
                    @yield('footer')

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

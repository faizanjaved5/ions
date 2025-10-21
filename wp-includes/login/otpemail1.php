<?php
// otpemail.php - Email template for OTP
function get_otp_email($otp, $email) {
    return '
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .header { padding: 20px !important; }
            .content { padding: 20px !important; }
            .otp-code { font-size: 32px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; padding: 20px;">
        <tr>
            <td align="center">
                <table class="container" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <tr>
                        <td class="header" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); padding: 40px; text-align: center;">
                            <div style="width: 64px; height: 64px; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; margin: 0 auto 16px; display: inline-flex; align-items: center; justify-content: center;">
                                <svg width="32" height="32" fill="white" viewBox="0 0 24 24">
                                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                                </svg>
                            </div>
                            <h1 style="color: white; font-size: 28px; font-weight: bold; margin: 0 0 8px 0;">Verify Your Email</h1>
                        </td>
                    </tr>
                    <tr>
                        <td class="content" style="padding: 20px;">
                            <div style="background: linear-gradient(135deg, #f8fafc, #e2e8f0); border-radius: 16px; padding: 32px; margin-bottom: 32px; text-align: center; border: 1px solid #e5e7eb;">
                                <p style="color: #6b7280; font-size: 12px; font-weight: 500; margin: 0 0 16px 0; text-transform: uppercase; letter-spacing: 0.05em;">Your Verification Code</p>
                                <div style="background-color: #1f2937; color: #ffffff; border-radius: 12px; padding: 24px; margin: 0 auto; display: inline-block; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                                    <div class="otp-code" style="font-size: 40px; font-family: \'Courier New\', monospace; font-weight: bold; letter-spacing: 0.25em;">' . htmlspecialchars($otp) . '</div>
                                </div>
                                <p style="color: #6b7280; font-size: 12px; margin: 16px 0 0 0;">This code will expire in 10 minutes</p>
                            </div>
                            <div style="text-align: center; margin-bottom: 32px;">
                                <p style="color: #6b7280; margin: 0 0 8px 0;">Verification code sent to:</p>
                                <p style="color: #3b82f6; font-weight: 600; font-size: 16px; margin: 0;">' . htmlspecialchars($email) . '</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}
?>
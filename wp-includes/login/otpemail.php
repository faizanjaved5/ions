<?php
/**
 * OTP Email Template
 * Returns HTML content for OTP emails
 */

function get_otp_email($otp, $email) {
    $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your ION Login Code</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 40px 20px 20px; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); border-radius: 16px 16px 0 0;">
                            <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network" style="height: 60px; width: auto;" />
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="margin: 0 0 20px; font-size: 28px; font-weight: 700; color: #111827; text-align: center;">
                                Your Login Code
                            </h1>
                            
                            <p style="margin: 0 0 30px; font-size: 16px; line-height: 1.6; color: #4b5563; text-align: center;">
                                Use this code to complete your login to ION Console:
                            </p>
                            
                            <!-- OTP Code Box -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <div style="background: linear-gradient(135deg, #896948 0%, #a47e5a 100%); color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: 8px; padding: 20px 40px; border-radius: 12px; display: inline-block;">
                                            ' . htmlspecialchars($otp) . '
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 30px 0 20px; font-size: 14px; line-height: 1.6; color: #6b7280; text-align: center;">
                                This code will expire in <strong>10 minutes</strong>
                            </p>
                            
                            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                            
                            <!-- Security Notice -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #fef3c7; border-radius: 8px; padding: 16px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; font-size: 14px; color: #92400e;">
                                            <strong>🔒 Security Notice:</strong> Never share this code with anyone. ION staff will never ask for your login code.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 20px 0 0; font-size: 12px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                If you didn\'t request this code, please ignore this email or contact support if you have concerns.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f9fafb; border-radius: 0 0 16px 16px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; color: #6b7280; text-align: center;">
                                © ' . date('Y') . ' ION Network. All rights reserved.<br>
                                <a href="https://ions.com" style="color: #896948; text-decoration: none;">ions.com</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    
    return $html;
}
?>
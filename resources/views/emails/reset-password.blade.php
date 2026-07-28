<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .email-header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .email-body {
            background-color: white;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #856404;
        }
        .code-box {
            text-align: center;
            margin: 25px 0;
            padding: 20px;
            background-color: #eef2ff;
            border: 1px dashed #6366f1;
            border-radius: 8px;
        }
        .code-box .code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #4f46e5;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Password Reset Request</h1>
        </div>
        <div class="email-body">
            <p>Hello {{ $userName }},</p>

            <p>We received a request to reset your password. Enter this verification code in the app to continue:</p>

            <div class="code-box">
                <span class="code">{{ $code }}</span>
            </div>

            <div class="warning">
                <strong>⚠️ Security Note:</strong> This code will expire in 10 minutes. If you didn't request this, please ignore this email.
            </div>

            <p>Best regards,<br>
            <strong>SAIMS Team</strong></p>

            <div class="footer">
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </div>
</body>
</html>

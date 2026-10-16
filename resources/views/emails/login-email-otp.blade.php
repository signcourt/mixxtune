<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>MIXX TUNE Login OTP</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:560px;margin:40px auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;">
        <h2 style="margin:0 0 20px;">MIXX TUNE Login Verification</h2>

        <p>Hello {{ $userName }},</p>

        <p>Use the following OTP to complete your login:</p>

        <div style="font-size:34px;font-weight:700;letter-spacing:8px;text-align:center;padding:24px;margin:24px 0;background:#f1f5f9;border-radius:12px;">
            {{ $otp }}
        </div>

        <p>This OTP is valid for <strong>5 minutes</strong>.</p>

        <p>If you did not attempt to log in, you can ignore this email.</p>

        <p style="margin-top:30px;color:#64748b;font-size:13px;">
            MIXX TUNE
        </p>
    </div>
</body>
</html>

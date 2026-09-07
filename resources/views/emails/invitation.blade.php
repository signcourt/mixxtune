<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>

<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f8;padding:40px 15px;">
    <tr>
        <td align="center">

            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">

                <!-- Header -->
                <tr>
                    <td style="padding:26px 30px;text-align:center;border-bottom:1px solid #eef0f2;">
                        <div style="font-size:24px;font-weight:700;color:#111827;">
                            Mixx Tune
                        </div>

                        <div style="margin-top:5px;font-size:13px;color:#6b7280;">
                            Music Distribution &amp; Management
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style="padding:35px 32px;">

                        <h1 style="margin:0 0 18px;font-size:24px;line-height:1.3;color:#111827;">
                            You're invited
                        </h1>

                        <p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#4b5563;">
                            Hello {{ $name }},
                        </p>

                        <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#4b5563;">
                            You have been invited to join
                            <strong style="color:#111827;">Mixx Tune</strong>
                            @if($roleName)
                                as a <strong style="color:#111827;">{{ $roleName }}</strong>.
                            @endif
                        </p>

                        @if($username || $email)
                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background:#f8fafc;border-radius:10px;margin-bottom:25px;">
                            <tr>
                                <td style="padding:16px 18px;">
                                    @if($username)
                                    <div style="font-size:13px;color:#6b7280;">
                                        Username
                                    </div>
                                    <div style="margin-top:4px;font-size:15px;font-weight:600;color:#111827;">
                                        {{ '@'.$username }}
                                    </div>
                                    @endif

                                    @if($email)
                                    <div style="margin-top:12px;font-size:13px;color:#6b7280;">
                                        Email
                                    </div>
                                    <div style="margin-top:4px;font-size:15px;font-weight:600;color:#111827;">
                                        {{ $email }}
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        @endif

                        <p style="margin:0 0 25px;font-size:15px;line-height:1.7;color:#4b5563;">
                            Click the button below to verify your invitation and set up your account.
                        </p>

                        <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 28px;">
                            <tr>
                                <td align="center" style="border-radius:8px;background:#111827;">
                                    <a href="{{ $actionUrl }}"
                                       style="display:inline-block;padding:13px 24px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;border-radius:8px;">
                                        {{ $actionText }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 10px;font-size:13px;line-height:1.6;color:#6b7280;">
                            This invitation link will expire in <strong>7 days</strong>.
                        </p>

                        <p style="margin:0;font-size:13px;line-height:1.6;color:#9ca3af;">
                            If you were not expecting this invitation, you can safely ignore this email.
                        </p>

                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:20px 30px;text-align:center;background:#fafafa;border-top:1px solid #eef0f2;">
                        <div style="font-size:13px;color:#6b7280;">
                            © {{ date('Y') }} Mixx Tune
                        </div>

                        <div style="margin-top:5px;font-size:12px;color:#9ca3af;">
                            This is an automated email. Please do not reply.
                        </div>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>

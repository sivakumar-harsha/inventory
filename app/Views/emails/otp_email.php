<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Password Reset Verification Code</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
<tr>
<td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
<tr>
<td style="background:#2563eb;padding:24px 32px;">
<span style="color:#ffffff;font-size:18px;font-weight:700;">A&amp;A Inventory ERP</span>
</td>
</tr>
<tr>
<td style="padding:32px;">
<p style="margin:0 0 16px;color:#1e293b;font-size:15px;">Hello,</p>
<p style="margin:0 0 16px;color:#1e293b;font-size:15px;line-height:1.5;">
We received a request to reset your password for <strong>A&amp;A Inventory ERP</strong>.
</p>
<p style="margin:0 0 8px;color:#1e293b;font-size:15px;">Your verification code is:</p>
<div style="text-align:center;margin:20px 0;">
<span style="display:inline-block;background:#eff6ff;border:1px dashed #2563eb;color:#1d4ed8;font-size:28px;font-weight:700;letter-spacing:6px;padding:14px 24px;border-radius:8px;">
<?= esc($otp) ?>
</span>
</div>
<p style="margin:0 0 16px;color:#64748b;font-size:13px;">This code is valid for <strong>10 minutes</strong>.</p>
<p style="margin:0 0 16px;color:#64748b;font-size:13px;">If you did not request this password reset, please ignore this email.</p>
<p style="margin:24px 0 0;color:#1e293b;font-size:15px;">Regards,<br><strong>A&amp;A Inventory ERP</strong></p>
</td>
</tr>
<tr>
<td style="background:#f8fafc;padding:16px 32px;text-align:center;">
<span style="color:#94a3b8;font-size:11px;">This is an automated message. Please do not reply to this email.</span>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>

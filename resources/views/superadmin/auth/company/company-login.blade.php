<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Set Your ERP Password</title>
</head>

<body style="font-family: Arial, Helvetica, sans-serif; background:#f4f6f8; padding:30px">

<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0"
       style="background:#ffffff;border-radius:8px;padding:30px">

<tr>
<td>

<h2 style="color:#222;margin-bottom:10px;">
Welcome to {{ $company->name }} ERP 🎉
</h2>

<p style="color:#444;font-size:15px;">
Hello,
</p>

<p style="color:#444;font-size:15px;">
Your company account has been created successfully.
To get started, you must first set your password.
</p>

<table style="margin:20px 0;font-size:14px;">
<tr>
<td style="padding:6px 0;"><strong>Email:</strong></td>
<td style="padding:6px 0;">{{ $user->email }}</td>
</tr>
<tr>
<td style="padding:6px 0;"><strong>Company:</strong></td>
<td style="padding:6px 0;">{{ $company->name }}</td>
</tr>
</table>

<p style="text-align:center;margin:30px 0;">
<a href="{{ $setPasswordUrl }}"
   style="
        background:#ff0057;
        color:#ffffff;
        padding:14px 28px;
        text-decoration:none;
        border-radius:6px;
        font-weight:bold;
        display:inline-block;
   ">
Set Your Password
</a>
</p>

<p style="font-size:14px;color:#555;">
This link will expire in <strong>24 hours</strong>.
</p>

<p style="font-size:13px;color:#666;">
If the button does not work, copy and paste this URL into your browser:
</p>

<p style="word-break:break-all;font-size:12px;color:#333;">
{{ $setPasswordUrl }}
</p>

<hr style="margin:30px 0;border:none;border-top:1px solid #e5e7eb;">

<p style="font-size:12px;color:#888;">
If you did not request this account, you can safely ignore this email.
</p>

<p style="font-size:12px;color:#888;">
© {{ date('Y') }} {{ $company->name }} ERP. All rights reserved.
</p>

</td>
</tr>
</table>

</td>
</tr>
</table>

</body>
</html>

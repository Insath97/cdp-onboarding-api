<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
    <!--[if mso]>
    <style>table,td{font-family:Arial,sans-serif!important}</style>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#0F172A;font-family:'Segoe UI',Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0F172A;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

                    <!-- Logo Header -->
                    <tr>
                        <td align="center" style="padding:32px 0 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-right:12px;vertical-align:middle;">
                                        <img src="{{ asset('images/vector-logo.png') }}" alt="VECTOR" width="48" height="48" style="display:block;border-radius:10px;">
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <span style="font-family:'Segoe UI',Arial,sans-serif;font-size:22px;font-weight:700;color:#FFFFFF;letter-spacing:4px;">VECTOR</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main Card -->
                    <tr>
                        <td style="background-color:#1E293B;border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;">
                            <!-- Green Top Bar -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="height:4px;background:linear-gradient(90deg,#458D45,#86C486,#458D45);font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>

                            <!-- Welcome Section -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:40px 40px 24px;">
                                        <h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#FFFFFF;font-family:'Segoe UI',Arial,sans-serif;">
                                            Welcome aboard, {{ $user['name'] }}! 🎉
                                        </h1>
                                        <p style="margin:0;color:#94A3B8;font-size:15px;line-height:1.6;">
                                            Your account has been successfully created. You're all set to begin your journey with <strong style="color:#86C486;">VECTOR</strong> Onboarding System.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Credentials Section -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:0 40px 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0F172A;border-radius:12px;border:1px solid rgba(255,255,255,0.06);">
                                            <tr>
                                                <td style="padding:24px;">
                                                    <h2 style="margin:0 0 16px;font-size:13px;font-weight:600;color:#458D45;letter-spacing:2px;text-transform:uppercase;font-family:'Segoe UI',Arial,sans-serif;">
                                                        Access Credentials
                                                    </h2>
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="padding:8px 0;color:#64748B;font-size:13px;width:110px;vertical-align:top;">Username</td>
                                                            <td style="padding:8px 0;color:#FFFFFF;font-size:14px;font-weight:600;">{{ $user['username'] }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding:8px 0;color:#64748B;font-size:13px;vertical-align:top;">Email</td>
                                                            <td style="padding:8px 0;color:#FFFFFF;font-size:14px;">{{ $user['email'] }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding:8px 0;color:#64748B;font-size:13px;vertical-align:top;">Password</td>
                                                            <td style="padding:8px 0;color:#CBD5E1;font-size:14px;font-style:italic;">Check the separate secure message for your password.</td>
                                                        </tr>
                                                        @if (isset($role) && !empty($role))
                                                        <tr>
                                                            <td style="padding:8px 0;color:#64748B;font-size:13px;vertical-align:top;">Role</td>
                                                            <td style="padding:8px 0;">
                                                                <span style="display:inline-block;background-color:rgba(69,141,69,0.15);color:#86C486;font-size:12px;font-weight:600;padding:4px 12px;border-radius:20px;border:1px solid rgba(69,141,69,0.2);">{{ $role }}</span>
                                                            </td>
                                                        </tr>
                                                        @endif
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Employee Details (Staff Only) -->
                            @if (isset($user['user_type']) && $user['user_type'] === 'staff')
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:0 40px 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0F172A;border-radius:12px;border:1px solid rgba(255,255,255,0.06);">
                                            <tr>
                                                <td style="padding:24px;">
                                                    <h2 style="margin:0 0 16px;font-size:13px;font-weight:600;color:#458D45;letter-spacing:2px;text-transform:uppercase;font-family:'Segoe UI',Arial,sans-serif;">
                                                        Employee Details
                                                    </h2>
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        @if (isset($branch_name) && !empty($branch_name))
                                                        <tr>
                                                            <td style="padding:6px 0;color:#64748B;font-size:13px;width:120px;">Branch</td>
                                                            <td style="padding:6px 0;color:#FFFFFF;font-size:14px;">{{ $branch_name }}</td>
                                                        </tr>
                                                        @endif
                                                        @if (isset($zone_name) && !empty($zone_name))
                                                        <tr>
                                                            <td style="padding:6px 0;color:#64748B;font-size:13px;">Zone</td>
                                                            <td style="padding:6px 0;color:#FFFFFF;font-size:14px;">{{ $zone_name }}</td>
                                                        </tr>
                                                        @endif
                                                        @if (isset($region_name) && !empty($region_name))
                                                        <tr>
                                                            <td style="padding:6px 0;color:#64748B;font-size:13px;">Region</td>
                                                            <td style="padding:6px 0;color:#FFFFFF;font-size:14px;">{{ $region_name }}</td>
                                                        </tr>
                                                        @endif
                                                        @if (isset($province_name) && !empty($province_name))
                                                        <tr>
                                                            <td style="padding:6px 0;color:#64748B;font-size:13px;">Province</td>
                                                            <td style="padding:6px 0;color:#FFFFFF;font-size:14px;">{{ $province_name }}</td>
                                                        </tr>
                                                        @endif
                                                        @if (isset($designation_name) && !empty($designation_name))
                                                        <tr>
                                                            <td style="padding:6px 0;color:#64748B;font-size:13px;">Designation</td>
                                                            <td style="padding:6px 0;color:#FFFFFF;font-size:14px;">{{ $designation_name }}</td>
                                                        </tr>
                                                        @endif
                                                        @if (isset($parent_name) && !empty($parent_name))
                                                        <tr>
                                                            <td style="padding:6px 0;color:#64748B;font-size:13px;">Reports To</td>
                                                            <td style="padding:6px 0;color:#FFFFFF;font-size:14px;">{{ $parent_name }}</td>
                                                        </tr>
                                                        @endif
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <!-- Login Button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding:8px 40px 40px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="border-radius:10px;background-color:#458D45;background:linear-gradient(135deg,#458D45,#5CA85C);">
                                                    <a href="{{ $login_url }}" target="_blank" style="display:inline-block;padding:14px 36px;font-family:'Segoe UI',Arial,sans-serif;font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:10px;">
                                                        Login to Your Account →
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 40px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="border-top:1px solid rgba(255,255,255,0.06);padding-top:24px;">
                                        <p style="margin:0 0 8px;color:#64748B;font-size:12px;line-height:1.5;text-align:center;">
                                            This is an automated message from <strong style="color:#86C486;">VECTOR</strong> Onboarding System. Please do not reply directly to this email.
                                        </p>
                                        <p style="margin:0;color:#475569;font-size:11px;text-align:center;">
                                            Created by: {{ $created_by }} &bull; {{ date('Y') }} VECTOR. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Bottom Spacer -->
                    <tr>
                        <td style="height:40px;"></td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

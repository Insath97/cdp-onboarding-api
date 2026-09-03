<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to {{ config('app.name') }}</title>
</head>
<body>
    <h2>Welcome to {{ config('app.name') }}, {{ $user['name'] }}!</h2>
    <p>Your professional account has been successfully created. You can access the platform using the credentials below:</p>

    <h3>Access Credentials</h3>
    <ul>
        <li><strong>Username:</strong> {{ $user['username'] }}</li>
        <li><strong>Email:</strong> {{ $user['email'] }}</li>
        <li><strong>Password:</strong> {{ $password }}</li>
        @if (isset($role) && !empty($role))
            <li><strong>Role:</strong> {{ $role }}</li>
        @endif
    </ul>

    @if (isset($user['user_type']) && $user['user_type'] === 'staff')
        <h3>Employee Details</h3>
        <ul>
            @if (isset($branch_name) && !empty($branch_name))
                <li><strong>Branch:</strong> {{ $branch_name }}</li>
            @endif
            @if (isset($zone_name) && !empty($zone_name))
                <li><strong>Zone:</strong> {{ $zone_name }}</li>
            @endif
            @if (isset($region_name) && !empty($region_name))
                <li><strong>Region:</strong> {{ $region_name }}</li>
            @endif
            @if (isset($province_name) && !empty($province_name))
                <li><strong>Province:</strong> {{ $province_name }}</li>
            @endif
            @if (isset($designation_name) && !empty($designation_name))
                <li><strong>Designation:</strong> {{ $designation_name }}</li>
            @endif
            @if (isset($parent_name) && !empty($parent_name))
                <li><strong>Reports To:</strong> {{ $parent_name }}</li>
            @endif
        </ul>
    @endif

    <p>
        <a href="{{ $login_url }}">Login to Your Account</a>
    </p>

    <hr>
    <p>This is an automated message. Please do not reply directly to this email.</p>
    <p>Created by: {{ $created_by }}</p>
</body>
</html>

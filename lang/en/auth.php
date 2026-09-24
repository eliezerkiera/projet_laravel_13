<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines (English)
    |--------------------------------------------------------------------------
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // OTP & Verification
    'otp_sent' => 'A verification code has been sent to your email address.',
    'otp_invalid' => 'The verification code is invalid.',
    'otp_expired' => 'The verification code has expired. Please request a new one.',
    'otp_max_attempts' => 'Maximum verification attempts exceeded. Please request a new code.',
    'otp_wait_resend' => 'Please wait :seconds seconds before requesting another code.',
    'invalid_token' => 'The verification token is invalid or has expired.',

    // Registration
    'registration_completed' => 'Your account has been registered successfully.',

    // Login & 2FA
    'login_otp_required' => 'Credentials verified. Please enter the verification code sent to your email.',
    'login_success' => 'Logged in successfully.',
    'pending_email_verification_required' => 'You must verify your new email address before logging in.',

    // Password Reset
    'password_reset_code_verified' => 'Verification code confirmed. You can now reset your password.',
    'password_reset_success' => 'Your password has been reset successfully.',

    // Profile & Password Change
    'profile_updated' => 'Profile updated successfully.',
    'email_change_otp_sent' => 'Profile updated. A verification code has been sent to your new email address.',
    'email_verified_success' => 'Email address verified and updated successfully.',
    'password_changed' => 'Password changed successfully.',

    // Logout & Account Deletion
    'logged_out' => 'Logged out successfully.',
    'account_deleted' => 'Your account has been deleted successfully.',

    // Device Management
    'device_disconnected' => 'Device disconnected successfully.',
    'other_devices_disconnected' => 'All other devices have been disconnected successfully.',
    'device_not_found' => 'Device session not found.',

    // Mail Notifications
    'mail' => [
        'subject' => 'Your Verification Code: :code',
        'greeting' => 'Hello :name,',
        'greeting_generic' => 'Hello,',
        'body' => 'Your verification code is:',
        'expiry' => 'This code will expire in :minutes minutes.',
        'warning' => 'If you did not initiate this request, no further action is required.',
    ],

];

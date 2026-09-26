<?php

use App\Http\Controllers\Api\V2\Auth\DeviceController;
use App\Http\Controllers\Api\V2\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V2\Auth\LoginController;
use App\Http\Controllers\Api\V2\Auth\ProfileController;
use App\Http\Controllers\Api\V2\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 2
|--------------------------------------------------------------------------
*/

Route::prefix('v2/auth')->group(function () {
    Route::middleware('throttle:auth-api')->group(function () {
        // -------------------------------------------------------------
        // Registration Flow (3 steps with OTP)
        // -------------------------------------------------------------
        Route::post('/register/send-code', [RegisterController::class, 'sendCode'])->middleware('throttle:auth-otp-request');
        Route::post('/register/resend-code', [RegisterController::class, 'resendCode'])->middleware('throttle:auth-otp-request');
        Route::post('/register/verify-code', [RegisterController::class, 'verifyCode'])->middleware('throttle:auth-otp-verification');
        Route::post('/register/complete', [RegisterController::class, 'complete'])->middleware('throttle:auth-otp-verification');

        // -------------------------------------------------------------
        // Login & 2FA Flow
        // -------------------------------------------------------------
        Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('/login/resend-code', [LoginController::class, 'resendCode'])->middleware('throttle:auth-otp-request');
        Route::post('/login/verify-code', [LoginController::class, 'verifyCode'])->middleware('throttle:auth-otp-verification');

        // -------------------------------------------------------------
        // Password Reset Flow (OTP)
        // -------------------------------------------------------------
        Route::post('/forgot-password/send-code', [ForgotPasswordController::class, 'sendCode'])->middleware('throttle:auth-otp-request');
        Route::post('/forgot-password/resend-code', [ForgotPasswordController::class, 'resendCode'])->middleware('throttle:auth-otp-request');
        Route::post('/forgot-password/verify-code', [ForgotPasswordController::class, 'verifyCode'])->middleware('throttle:auth-otp-verification');
        Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword'])->middleware('throttle:auth-otp-verification');

        // Guest pending email confirmation (if disconnected)
        Route::post('/verify-pending-email', [ProfileController::class, 'verifyPendingEmailGuest'])->middleware('throttle:auth-otp-verification');
    });

    // -------------------------------------------------------------
    // Authenticated Endpoints (Sanctum)
    // -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:auth-api'])->group(function () {
        // User Profile & Account Management
        Route::get('/me', [ProfileController::class, 'me']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/verify-email', [ProfileController::class, 'verifyEmail']);
        Route::put('/password', [ProfileController::class, 'changePassword']);
        Route::delete('/account', [ProfileController::class, 'destroy']);

        // Logout & Connected Devices Management
        Route::post('/logout', [DeviceController::class, 'logout']);
        Route::get('/devices', [DeviceController::class, 'index']);
        Route::delete('/devices/{id}', [DeviceController::class, 'destroy']);
        Route::delete('/devices', [DeviceController::class, 'destroyOthers']);
    });
});

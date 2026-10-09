<?php

use App\Http\Controllers\Api\V2\Auth\DeviceController;
use App\Http\Controllers\Api\V2\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V2\Auth\LoginController;
use App\Http\Controllers\Api\V2\Auth\ProfileController;
use App\Http\Controllers\Api\V2\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V2\Auth\RegisterController;
use App\Http\Controllers\Api\V2\CountryController;
use App\Http\Controllers\Api\V2\LocaleController;
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
        Route::post('/register/send-code', [RegisterController::class, 'sendCode'])->middleware('throttle:otp-send');
        Route::post('/register/resend-code', [RegisterController::class, 'resendCode'])->middleware('throttle:otp-send');
        Route::post('/register/verify-code', [RegisterController::class, 'verifyCode'])->middleware('throttle:otp-verify');
        Route::post('/register/complete', [RegisterController::class, 'complete'])->middleware('throttle:otp-verify');

        // -------------------------------------------------------------
        // Login & 2FA Flow
        // -------------------------------------------------------------
        Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login');
        Route::post('/login/resend-code', [LoginController::class, 'resendCode'])->middleware('throttle:otp-send');
        Route::post('/login/verify-code', [LoginController::class, 'verifyCode'])->middleware('throttle:otp-verify');

        // -------------------------------------------------------------
        // Password Reset Flow (OTP)
        // -------------------------------------------------------------
        Route::post('/forgot-password/send-code', [ForgotPasswordController::class, 'sendCode'])->middleware('throttle:otp-send');
        Route::post('/forgot-password/resend-code', [ForgotPasswordController::class, 'resendCode'])->middleware('throttle:otp-send');
        Route::post('/forgot-password/verify-code', [ForgotPasswordController::class, 'verifyCode'])->middleware('throttle:otp-verify');
        Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword'])->middleware('throttle:sensitive');

        // Guest pending email confirmation (if disconnected)
        Route::post('/verify-pending-email', [ProfileController::class, 'verifyPendingEmailGuest'])->middleware('throttle:otp-verify');

        // Refresh Token (public endpoint - no auth required)
        Route::post('/refresh', [RefreshTokenController::class, 'refresh'])->middleware('throttle:refresh');
    });

    // -------------------------------------------------------------
    // Authenticated Endpoints (Sanctum)
    // -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:auth-api'])->group(function () {
        // User Profile & Account Management
        Route::get('/me', [ProfileController::class, 'me']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/verify-email', [ProfileController::class, 'verifyEmail']);
        Route::put('/password', [ProfileController::class, 'changePassword'])->middleware('throttle:sensitive');
        Route::delete('/account', [ProfileController::class, 'destroy'])->middleware('throttle:sensitive');

        // Logout & Connected Devices Management
        Route::post('/logout', [DeviceController::class, 'logout']);
        Route::get('/devices', [DeviceController::class, 'index']);
        Route::delete('/devices/{id}', [DeviceController::class, 'destroy']);
        Route::delete('/devices', [DeviceController::class, 'destroyOthers']);
    });
});

Route::prefix('v2')->group(function () {
    // Public locale endpoint
    Route::get('/locale', [LocaleController::class, 'show'])->name('v2.locale.show');

    // Countries endpoint
    Route::get('/countries/active', [CountryController::class, 'index']);

    // Authenticated locale update endpoint
    Route::middleware(['auth:sanctum', 'throttle:auth-api'])->patch('/locale', [LocaleController::class, 'update']);
});

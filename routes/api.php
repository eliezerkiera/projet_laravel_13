<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function(){
    $monTableau = ['nom' => 'Dupont', 'age' => 30];
    
    return response()->json($monTableau);
});



Route::prefix('v1')->group(function () {

    //test
    
    Route::get('/test', function(){
        $monTableau = ['nom' => 'Dupont', 'age' => 30];
        
        return response()->json($monTableau);
    });

    // Routes publiques avec rate limiting
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1'); // 5 tentatives par minute

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 tentatives par minute

    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,1'); // 3 tentatives par minute

    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1');

    // Vérification email (publique + signature)
    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmailByLink'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Publique
    Route::post('/auth/email/verify-code', [AuthController::class, 'verifyEmailWithCode'])
    ->middleware('throttle:5,1');

    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:3,1');

Route::post('/auth/reset-password-with-code', [AuthController::class, 'resetPasswordWithCode'])
    ->middleware('throttle:5,1');

    // Routes protégées
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);

        // Email verification
        Route::post('/auth/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
            ->middleware('throttle:3,1');
        // Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        //     ->middleware(['signed', 'throttle:6,1'])
        //     ->name('verification.verify');

        // Multi-device
        Route::get('/auth/devices', [AuthController::class, 'devices']);
        Route::delete('/auth/devices/{tokenId}', [AuthController::class, 'revokeDevice']);
        Route::post('/auth/devices/revoke-others', [AuthController::class, 'revokeOtherDevices']);

        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('/auth/password', [AuthController::class, 'updatePassword']);
        Route::delete('/auth/account', [AuthController::class, 'deleteAccount']);


        // Protégée (renvoi du code)
        Route::post('/auth/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
            ->middleware('throttle:3,1');
    });
});

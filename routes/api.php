<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function(){
    $monTableau = ['nom' => 'Dupont', 'age' => 30];
    
    return response()->json($monTableau);
});
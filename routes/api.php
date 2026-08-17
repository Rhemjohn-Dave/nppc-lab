<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()?->toInertiaArray();
    });

    Route::get('/health', function () {
        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
        ]);
    });
});

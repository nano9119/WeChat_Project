<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileApiController;
use App\Http\Controllers\Api\MessageApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

    // ✅ مسارات عامة (بدون توثيق)
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    // ✅ مسارات للمستخدمين المسجلين فقط
    Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn() => auth()->user());

    // Messages
    Route::apiResource('messages', MessageApiController::class);

    // Files
    Route::apiResource('files', FileApiController::class);
    Route::post('/files/upload/{id}', [FileApiController::class, 'update']);
});

// ✅ مسارات للمشرف فقط
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('users', UserApiController::class);
});

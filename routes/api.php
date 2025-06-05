<?php
// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TodoController;

Route::post('register', [AuthController::class, 'register']);
Route::post('/request-otp-login', [AuthController::class, 'requestOtpLogin']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('me', [AuthController::class, 'me']);

Route::middleware('auth:api')->group(function () {
    Route::get('todos', [TodoController::class, 'index']);
    Route::post('todos', [TodoController::class, 'store']);
    Route::get('todos/{id}', [TodoController::class, 'show']);
    Route::put('todos/{id}', [TodoController::class, 'update']);
    Route::delete('todos/{id}', [TodoController::class, 'destroy']);
});


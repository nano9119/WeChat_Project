<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; 
use App\Http\Controllers\Api\MessageApiController;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return view('welcometochat');
});

// صفحة تسجيل الدخول المخصصة
Route::get('/interfacelogin', function() {
    return view('interfacelogin'); // Blade الخاصة بك
})->name('interfacelogin');

Route::post('/login', [AuthController::class, 'login'])->name('login'); 
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
Route::get('/chat', [ChatController::class, 'index'])->name('chat');
Route::post('/send-message', [ChatController::class, 'sendMessage'])->name('chat.sendMessage');
Route::post('/send-file', [ChatController::class, 'sendFile'])->name('chat.sendFile');

//Route::get('/chat', [MessageApiController::class, 'chat'])->name('chat')->middleware('auth');
Route::post('/messages', [MessageApiController::class, 'store'])->name('create');

Route::delete('/chat/message/{id}', [ChatController::class, 'destroy'])->name('chat.deleteMessage');
Route::put('/chat/message/{id}', [ChatController::class, 'update'])->name('chat.updateMessage');

Route::delete('/chat/file/{id}', [ChatController::class, 'deleteFile'])->name('chat.deleteFile');
Route::post('/chat/file/{id}/update', [ChatController::class, 'updateFile'])->name('chat.updateFile');

Route::get('/exit', function () {
    return view('exit');
})->name('exit');

Route::get('/wait', function () {
    return view('wait');
})->name('wait');

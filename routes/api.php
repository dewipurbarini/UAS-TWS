<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PengumumanController;
use Illuminate\Support\Facades\Route;

// Route API tanpa awalan /api. File ini dimuat dengan middleware group "api".

// Login bersifat publik. Tidak memakai jwt.auth.
Route::post('/login', [AuthController::class, 'login']);

// Semua route di dalam grup ini butuh token yang sah.
Route::middleware('jwt.auth')->group(function () {
    // Data akun dari token. Boleh admin maupun user.
    Route::get('/me', [AuthController::class, 'me']);

    // Membaca pengumuman boleh semua akun yang sudah login.
    Route::get('/pengumuman', [PengumumanController::class, 'index']);
    Route::get('/pengumuman/{id}', [PengumumanController::class, 'show']);

    // Menulis data dan melihat log hanya untuk admin. role.admin berjalan setelah jwt.auth.
    Route::middleware('role.admin')->group(function () {
        Route::post('/pengumuman', [PengumumanController::class, 'store']);
        Route::put('/pengumuman/{id}', [PengumumanController::class, 'update']);
        Route::delete('/pengumuman/{id}', [PengumumanController::class, 'destroy']);
        Route::get('/log', [LogController::class, 'index']);
    });
});

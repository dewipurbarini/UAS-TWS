<?php

use Illuminate\Support\Facades\Route;

// Halaman depan bawaan Laravel. Client tugas ada di /client/ (file statis), bukan di route ini.
Route::get('/', function () {
    // view welcome tidak diubah agar file bawaan Laravel tetap utuh.
    return view('welcome');
});

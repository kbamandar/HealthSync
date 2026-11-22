<?php

use App\Http\Controllers\SecurityWellKnownController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/.well-known/security-cert.json', [SecurityWellKnownController::class, 'certificate'])
    ->name('well-known.security-cert');

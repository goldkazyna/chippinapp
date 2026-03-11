<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/telegram-auth', function () {
    return view('telegram-auth');
});

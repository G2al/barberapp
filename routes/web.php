<?php

use Illuminate\Support\Facades\Route;

// Homepage (opzionale)
Route::get('/', function () {
    return view('welcome');
});

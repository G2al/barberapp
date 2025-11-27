<?php

use Illuminate\Support\Facades\Route;

// App Login/Welcome Page
Route::get('/', function () {
    return file_get_contents(public_path('index.html'));
});

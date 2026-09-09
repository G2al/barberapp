<?php

use Illuminate\Support\Facades\Route;

// Customer pages can be disabled on backend-only deployments.
$frontendPages = [
    'index', 'dashboard', 'register', 'forgot-password',
    'reset-password', 'my-bookings', 'products',
];

$serveFrontendPage = static function (string $page) {
    abort_unless(config('app.frontend_enabled'), 404);

    return response()->file(public_path($page.'.html'));
};

Route::get('/', static function () use ($serveFrontendPage) {
    if (!config('app.frontend_enabled')) {
        return redirect('/admin');
    }

    return $serveFrontendPage('index');
});

foreach ($frontendPages as $page) {
    Route::get('/'.$page.'.html', static function () use ($page, $serveFrontendPage) {
        return $serveFrontendPage($page);
    });
}

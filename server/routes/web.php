<?php

use Illuminate\Support\Facades\Route;

// The React build lives in public/app (assets under /app/assets are real
// files served directly). Any GET that isn't /api/* or a real file returns
// the SPA entry so client-side routing works on deep links.
Route::get('/', function () {
    return response()->file(public_path('app/index.html'));
});

Route::fallback(function () {
    if (request()->is('api/*')) {
        return response()->json(['message' => 'Not Found'], 404);
    }

    return response()->file(public_path('app/index.html'));
});

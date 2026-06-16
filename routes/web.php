<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (is_file(public_path('index.html'))) {
        return response()->file(public_path('index.html'));
    }

    return view('welcome');
});

Route::get('/{path}', function () {
    if (is_file(public_path('index.html'))) {
        return response()->file(public_path('index.html'));
    }

    abort(404);
})->where('path', '^(?!api|storage).*$');

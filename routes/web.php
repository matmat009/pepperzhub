<?php

use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('products', [ProductController::class, 'index'])
            ->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])
            ->name('products.create');
        Route::get('products/{product}', [ProductController::class, 'show'])
            ->whereNumber('product')
            ->name('products.show');
        Route::post('products', [ProductController::class, 'store'])
            ->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])
            ->whereNumber('product')
            ->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->whereNumber('product')
            ->name('products.destroy');
    });
});

require __DIR__.'/settings.php';

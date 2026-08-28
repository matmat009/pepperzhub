<?php

use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

/*
 * Public storefront. No auth middleware anywhere in this file — every route
 * here is reachable by an anonymous visitor.
 *
 * The root keeps the bare `home` name rather than taking the `storefront.`
 * prefix: AppSidebar and the four auth layouts already import `home` from the
 * Wayfinder barrel, and renaming it would break all five for no gain.
 */
Route::get('/', [ProductController::class, 'home'])->name('home');

Route::name('storefront.')->group(function () {
    Route::get('products', [ProductController::class, 'index'])
        ->name('products.index');

    // Slug rather than id, so the public URL stays readable. Constrained to
    // keep it clear of any future static /products/* segment.
    Route::get('products/{slug}', [ProductController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name('products.show');

    // Dummy UI for now — no cart or order schema exists yet, so these render
    // straight to their pages and hold their state client-side.
    Route::inertia('cart', 'storefront/Cart')->name('cart');
    Route::inertia('checkout', 'storefront/Checkout')->name('checkout');
    Route::inertia('order-confirmation', 'storefront/OrderConfirmation')
        ->name('confirmation');
    Route::inertia('track', 'storefront/TrackOrder')->name('track');
});

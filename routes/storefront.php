<?php

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\OrderConfirmationController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\TrackOrderController;
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

    /*
     * Cart lives in the session. Names and paths are unchanged from the stub
     * routes these replaced — Wayfinder helpers and <Link> usages across the
     * nav, the added-to-cart popover and the cart page already point at them.
     */
    Route::get('cart', [CartController::class, 'show'])->name('cart');
    Route::post('cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('cart', [CartController::class, 'update'])->name('cart.update');
    Route::delete('cart', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    /*
     * A random per-order token, not the id or the PZH- reference — the page has
     * to survive a refresh, and a sequential id in the URL would let one
     * customer read another's confirmation by counting.
     */
    Route::get('order-confirmation/{token}', [OrderConfirmationController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('confirmation');

    Route::get('track', [TrackOrderController::class, 'show'])->name('track');
    // Public, unauthenticated and guessable by design, so it is throttled.
    Route::post('track', [TrackOrderController::class, 'lookup'])
        ->middleware('throttle:6,1')
        ->name('track.lookup');
});

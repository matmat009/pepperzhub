<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/storefront.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('products', [ProductController::class, 'index'])
            ->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])
            ->name('products.create');

        // Static segments must be declared before products/{product}; the
        // whereNumber constraint already keeps them apart, but order makes the
        // intent obvious.
        Route::get('products/categories', [CategoryController::class, 'index'])
            ->name('products.categories.index');
        Route::post('products/categories', [CategoryController::class, 'store'])
            ->name('products.categories.store');
        Route::put('products/categories/{category}', [CategoryController::class, 'update'])
            ->whereNumber('category')
            ->name('products.categories.update');
        Route::delete('products/categories/{category}', [CategoryController::class, 'destroy'])
            ->whereNumber('category')
            ->name('products.categories.destroy');

        Route::get('products/inventory', [InventoryController::class, 'index'])
            ->name('products.inventory.index');
        Route::post('products/inventory/{product}/adjust', [InventoryController::class, 'adjust'])
            ->whereNumber('product')
            ->name('products.inventory.adjust');

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

        /*
         * Orders. Reads are constrained to numbers so they cannot shadow any
         * future static /orders/* segment; the action posts carry an id from
         * the detail page and need no constraint of their own.
         */
        Route::get('orders', [OrderController::class, 'index'])
            ->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])
            ->whereNumber('order')
            ->name('orders.show');
        // Private disk, streamed inline. Protected by this group's
        // auth/verified middleware — there is no public path to it.
        Route::get('orders/{order}/payment-proof', [OrderController::class, 'paymentProof'])
            ->whereNumber('order')
            ->name('orders.payment-proof');

        Route::post('orders/{order}/verify-payment', [OrderController::class, 'verifyPayment'])
            ->name('orders.verify-payment');
        Route::post('orders/{order}/reject-payment', [OrderController::class, 'rejectPayment'])
            ->name('orders.reject-payment');
        Route::post('orders/{order}/processing', [OrderController::class, 'markProcessing'])
            ->name('orders.processing');
        Route::post('orders/{order}/ship', [OrderController::class, 'markShipped'])
            ->name('orders.ship');
        Route::post('orders/{order}/complete', [OrderController::class, 'markCompleted'])
            ->name('orders.complete');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->name('orders.cancel');
    });
});

require __DIR__.'/settings.php';

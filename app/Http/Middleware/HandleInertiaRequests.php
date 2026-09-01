<?php

namespace App\Http\Middleware;

use App\Models\ProductVariant;
use App\Support\SessionCart;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            /*
             * The navbar badge needs the cart count on every storefront page,
             * and the cart now lives in the session rather than the browser.
             * Summed straight off the session so this costs no query — the
             * cart page itself does the hydrating.
             */
            'cartCount' => array_sum(SessionCart::raw()),
            /*
             * Shared rather than passed per page: the storefront card's badge
             * and the admin's low-stock tile both need it, and two pages each
             * declaring their own copy is how they drifted apart in the first
             * place. A constant, so this costs nothing.
             */
            'lowStockThreshold' => ProductVariant::LOW_STOCK_THRESHOLD,
        ];
    }
}

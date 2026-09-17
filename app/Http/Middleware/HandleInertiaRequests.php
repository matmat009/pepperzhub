<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
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
        $shared = [
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
            /*
             * The footer is on every storefront page and the FAQ contact panel
             * uses these values, so they cannot come from any one page's props
             * without every other page losing them. Shared for the same reason
             * as the low-stock threshold, and read through the matching
             * useSiteSettings composable.
             *
             * current() get-or-creates, so this is always an object and the
             * client only ever checks whether an individual field is set.
             */
            'siteSettings' => SiteSetting::current()->only([
                'contact_email',
                'contact_phone',
                'contact_address',
                'facebook_url',
                'instagram_url',
                'tiktok_url',
            ]),
        ];

        if ($this->shouldSharePendingOrdersCount($request)) {
            /*
             * The sidebar renders on every admin page, so the count it badges
             * cannot come from any one page's props — Orders/Index would leave
             * every other screen showing a stale number, or none at all.
             *
             * Deliberately narrower than "not finished": just placed, payment
             * not yet looked at. That is the queue the operator actually has to
             * act on, and it empties as they work. Counting everything short of
             * completed would badge orders already in hand and never reach
             * zero, which is how a badge stops being read at all. Same reason
             * the low-stock badge counts what is running out rather than the
             * whole catalogue.
             *
             * One COUNT, no rows hydrated, and the two equality predicates are
             * the orders table's composite (payment_status, order_status)
             * index in that order. The closure also keeps the query unevaluated
             * when downstream authorization redirects instead of rendering an
             * admin Inertia page.
             */
            $shared['pendingOrdersCount'] = fn (): int => Order::query()
                ->where('payment_status', 'unverified')
                ->where('order_status', 'pending')
                ->count();
        }

        return $shared;
    }

    /**
     * Only pages rendered inside the authenticated admin shell need its badge.
     *
     * Route identity is checked as well as authentication: the operator can
     * browse the public storefront while signed in, and that must not turn a
     * public response into an admin response. Verification mirrors the route
     * groups; Profile intentionally remains available under its existing
     * auth-only rule.
     */
    private function shouldSharePendingOrdersCount(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($request->routeIs('profile.edit')) {
            return true;
        }

        return $user->hasVerifiedEmail()
            && $request->routeIs('dashboard', 'admin.*', 'security.edit', 'appearance.edit');
    }
}

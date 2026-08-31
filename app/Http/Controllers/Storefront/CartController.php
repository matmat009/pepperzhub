<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Session-backed cart.
 *
 * The client never sends a price. It sends a variant id and a quantity; every
 * figure on the page comes back from the database on the next render.
 */
class CartController extends Controller
{
    public function show(): Response
    {
        $cart = SessionCart::hydrate();

        return Inertia::render('storefront/Cart', [
            'lines' => $cart['lines'],
            'subtotal' => $cart['subtotal'],
        ]);
    }

    /**
     * Add a variant, merging into the existing line rather than duplicating it.
     *
     * Quantity is clamped to live stock. Asking for more than exists adds what
     * is available and says why, rather than failing silently or pretending to
     * have reserved stock that is not there.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id'),
            ],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $variant = ProductVariant::query()
            ->whereKey($data['variant_id'])
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->with('product')
            ->first();

        if (! $variant) {
            $this->toast('That product is no longer available.', 'error');

            return back();
        }

        $stock = (int) $variant->stock;

        if ($stock < 1) {
            $this->toast("{$variant->product->name} is out of stock.", 'error');

            return back();
        }

        $cart = SessionCart::raw();
        $requested = ($cart[$variant->id] ?? 0) + (int) ($data['quantity'] ?? 1);
        $granted = min($requested, $stock);

        $cart[$variant->id] = $granted;
        SessionCart::put($cart);

        if ($granted < $requested) {
            $this->toast(
                "Only {$granted} of {$variant->product->name} left — we added what we could.",
                'warning',
            );
        }

        return back();
    }

    /**
     * Set an explicit quantity. Zero or below removes the line, which is what
     * the stepper's minus button expects at 1.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $cart = SessionCart::raw();
        $variantId = (int) $data['variant_id'];

        // A variant that is not already in the cart cannot be set from here;
        // adding goes through store().
        if (! array_key_exists($variantId, $cart)) {
            return back();
        }

        $quantity = (int) $data['quantity'];

        if ($quantity < 1) {
            unset($cart[$variantId]);
            SessionCart::put($cart);

            return back();
        }

        $variant = ProductVariant::query()->whereKey($variantId)->with('product')->first();

        if (! $variant) {
            unset($cart[$variantId]);
            SessionCart::put($cart);

            return back();
        }

        $stock = (int) $variant->stock;
        $granted = min($quantity, $stock);

        if ($granted < 1) {
            unset($cart[$variantId]);
            SessionCart::put($cart);
            $this->toast("{$variant->product->name} is out of stock.", 'error');

            return back();
        }

        $cart[$variantId] = $granted;
        SessionCart::put($cart);

        if ($granted < $quantity) {
            $this->toast("Only {$granted} of {$variant->product->name} in stock.", 'warning');
        }

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
        ]);

        $cart = SessionCart::raw();
        unset($cart[(int) $data['variant_id']]);
        SessionCart::put($cart);

        return back();
    }
}

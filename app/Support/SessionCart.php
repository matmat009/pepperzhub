<?php

namespace App\Support;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * The cart, stored server-side in the session.
 *
 * The session holds `[variant_id => quantity]` and nothing else — no price, no
 * name, no image. Every read re-hydrates those from the database, so a client
 * can never influence what something costs, and a price change is reflected the
 * next time the cart is looked at.
 *
 * Shared by CartController and CheckoutController so the two cannot disagree
 * about what is in the cart or what it is worth.
 */
class SessionCart
{
    public const SESSION_KEY = 'storefront.cart';

    /**
     * Raw session contents, keyed by variant id.
     *
     * @return array<int, int>
     */
    public static function raw(): array
    {
        /** @var array<int|string, mixed> $stored */
        $stored = session()->get(self::SESSION_KEY, []);

        $clean = [];

        foreach ($stored as $variantId => $quantity) {
            $id = (int) $variantId;
            $qty = (int) $quantity;

            if ($id > 0 && $qty > 0) {
                $clean[$id] = $qty;
            }
        }

        return $clean;
    }

    /** @param array<int, int> $cart */
    public static function put(array $cart): void
    {
        session()->put(self::SESSION_KEY, array_filter($cart, fn (int $qty): bool => $qty > 0));
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Variants that are still buyable: the row exists and its parent product is
     * active. A draft or archived product silently drops out of the cart rather
     * than blocking checkout later.
     *
     * @param  array<int, int>  $cart
     * @return Collection<int, ProductVariant>
     */
    public static function variantsFor(array $cart): Collection
    {
        if ($cart === []) {
            return collect();
        }

        return ProductVariant::query()
            ->whereIn('id', array_keys($cart))
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->with(['product.images', 'product.category'])
            ->get()
            ->keyBy('id');
    }

    /**
     * Cart lines with live name/price/image/stock, plus the subtotal.
     *
     * Any session entry whose variant has disappeared or been unpublished is
     * dropped here and written back out, so the stale id does not linger.
     *
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float, count: int}
     */
    public static function hydrate(): array
    {
        $cart = self::raw();
        $variants = self::variantsFor($cart);

        $lines = [];
        $surviving = [];
        $subtotal = 0.0;

        foreach ($cart as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if (! $variant) {
                continue;
            }

            // Never show more than can actually be bought.
            $quantity = min($quantity, (int) $variant->stock);

            if ($quantity < 1) {
                continue;
            }

            $unitPrice = (float) $variant->price;
            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;
            $surviving[$variantId] = $quantity;

            $lines[] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product->id,
                'product_name' => $variant->product->name,
                'product_slug' => $variant->product->slug,
                'product_category' => $variant->product->category?->name ?? '',
                'variant_label' => $variant->label,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'stock' => (int) $variant->stock,
                'image_url' => $variant->product->images->first()?->url(),
            ];
        }

        if ($surviving !== $cart) {
            self::put($surviving);
        }

        return [
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
            'count' => array_sum($surviving),
        ];
    }
}

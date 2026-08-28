import { computed, reactive } from 'vue';
import type { Product } from '@/pages/admin/products/all-products/types';

/**
 * Client-side stand-in for a real cart.
 *
 * There is no cart or order schema yet, so nothing here is persisted — the
 * state lives for the life of the tab and resets on a full reload. It exists so
 * the navbar badge, the cart page and the checkout summary can agree with each
 * other while the backend catches up.
 *
 * When the schema lands, this module is the seam to replace: the pages only
 * touch the functions below, never the array itself.
 */
export type CartLine = {
    /** `${productId}:${variantId}` — a product in two formats is two lines. */
    key: string;
    productId: number;
    productName: string;
    productSlug: string;
    variantId: string;
    variantLabel: string;
    unitPrice: number;
    imageUrl: string | null;
    quantity: number;
};

/** Module scope, so every component shares one cart rather than a copy each. */
const lines = reactive<CartLine[]>([]);

const lineKey = (productId: number, variantId: string) =>
    `${productId}:${variantId}`;

export const useStorefrontCart = () => {
    const count = computed(() =>
        lines.reduce((sum, line) => sum + line.quantity, 0),
    );

    const subtotal = computed(() =>
        lines.reduce((sum, line) => sum + line.unitPrice * line.quantity, 0),
    );

    const isEmpty = computed(() => lines.length === 0);

    const add = (product: Product, variantId: string, quantity = 1) => {
        const variant = product.variants.find((item) => item.id === variantId);

        if (!variant) {
            return;
        }

        const key = lineKey(product.id, variantId);
        const existing = lines.find((line) => line.key === key);

        if (existing) {
            existing.quantity += quantity;

            return;
        }

        lines.push({
            key,
            productId: product.id,
            productName: product.name,
            productSlug: product.slug,
            variantId,
            variantLabel: variant.label,
            unitPrice: variant.price,
            imageUrl: product.images[0]?.url ?? null,
            quantity,
        });
    };

    /** Dropping to zero removes the line, which is what the stepper expects. */
    const setQuantity = (key: string, quantity: number) => {
        const line = lines.find((item) => item.key === key);

        if (!line) {
            return;
        }

        if (quantity < 1) {
            remove(key);

            return;
        }

        line.quantity = quantity;
    };

    const remove = (key: string) => {
        const index = lines.findIndex((line) => line.key === key);

        if (index !== -1) {
            lines.splice(index, 1);
        }
    };

    const clear = () => {
        lines.splice(0, lines.length);
    };

    return { lines, count, subtotal, isEmpty, add, setQuantity, remove, clear };
};

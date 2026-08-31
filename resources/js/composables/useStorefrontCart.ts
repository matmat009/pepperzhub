import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { Product } from '@/pages/admin/products/all-products/types';
import {
    destroy as cartDestroy,
    store as cartStore,
    update as cartUpdate,
} from '@/routes/storefront/cart';

/**
 * Thin client over the server-side cart.
 *
 * The cart lives in the Laravel session; this holds no quantities of its own.
 * Mutations are Inertia visits, and the authoritative numbers come back as
 * props — the badge from the shared `cartCount`, the lines from the cart page's
 * own props. That is deliberate: the client never gets a say in price.
 *
 * This replaces the localStorage version. Server state is what a second device,
 * a refresh, and checkout all have to agree on, and only one of them can be the
 * source of truth.
 */
export type CartLine = {
    variant_id: number;
    product_id: number;
    product_name: string;
    product_slug: string;
    product_category: string;
    variant_label: string;
    unit_price: number;
    quantity: number;
    line_total: number;
    /** Live stock, so the stepper can stop at what is actually available. */
    stock: number;
    image_url: string | null;
};

/**
 * What the "Added to Cart" popover shows. Purely presentational and purely
 * client-side — the real add already happened on the server by the time this is
 * set, so it never has to agree with anything.
 */
export type AddedSummary = {
    productName: string;
    variantLabel: string;
    unitPrice: number;
    imageUrl: string | null;
};

const justAdded = ref<AddedSummary | null>(null);

export const useStorefrontCart = () => {
    const page = usePage();

    /** Shared from HandleInertiaRequests, so every page has it. */
    const count = computed<number>(
        () => (page.props.cartCount as number | undefined) ?? 0,
    );

    /** Inertia options common to every cart mutation. */
    const visitOptions = {
        preserveScroll: true,
        preserveState: true,
    } as const;

    /**
     * `onSuccess` matters for Buy Now: the add is a round trip now, so
     * navigating to checkout has to wait for it rather than racing it.
     */
    const add = (
        product: Product,
        variantId: string,
        quantity = 1,
        options: { onSuccess?: () => void; confirm?: boolean } = {},
    ) => {
        const variant = product.variants.find((item) => item.id === variantId);

        if (!variant) {
            return;
        }

        const { onSuccess, confirm = true } = options;

        router.post(
            cartStore().url,
            { variant_id: Number(variantId), quantity },
            {
                ...visitOptions,
                onSuccess: () => {
                    if (confirm) {
                        justAdded.value = {
                            productName: product.name,
                            variantLabel: variant.label,
                            unitPrice: variant.price,
                            imageUrl: product.images[0]?.url ?? null,
                        };
                    }

                    onSuccess?.();
                },
            },
        );
    };

    /** Dropping to zero removes the line, which is what the stepper expects. */
    const setQuantity = (variantId: number, quantity: number) => {
        router.patch(
            cartUpdate().url,
            { variant_id: variantId, quantity },
            visitOptions,
        );
    };

    const remove = (variantId: number) => {
        router.delete(cartDestroy().url, {
            ...visitOptions,
            data: { variant_id: variantId },
        });
    };

    const dismissAdded = () => {
        justAdded.value = null;
    };

    return { count, justAdded, add, setQuantity, remove, dismissAdded };
};

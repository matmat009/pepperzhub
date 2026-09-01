import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * The low-stock threshold, from the server.
 *
 * The value is App\Models\ProductVariant::LOW_STOCK_THRESHOLD, shared on every
 * Inertia response by HandleInertiaRequests. Read through here rather than
 * declared per page: the storefront's "Only N left" badge and the admin's
 * low-stock tile previously carried separate literals and disagreed, which is
 * the drift this exists to make impossible.
 *
 * No numeric fallback on purpose — a default here would be exactly the
 * hardcoded copy this removes. The prop is shared on every response, so its
 * absence is a bug worth surfacing rather than papering over.
 */
export const useLowStockThreshold = (): ComputedRef<number> => {
    const page = usePage();

    return computed(() => page.props.lowStockThreshold);
};

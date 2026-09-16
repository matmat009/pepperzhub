import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { SiteSettings } from '@/types/site-settings';

/**
 * The storefront's contact and social details, from the server.
 *
 * The values are the single App\Models\SiteSetting row, shared on every Inertia
 * response by HandleInertiaRequests. Read through here rather than hardcoded
 * per component: the footer's contact block, its social icons and the FAQ's
 * contact panel all depend on the operator's saved values.
 *
 * No fallback object on purpose — a default here would be exactly the hardcoded
 * copy this removes. The prop is shared on every response, so its absence is a
 * bug worth surfacing rather than papering over. Individual fields are null
 * until set, and callers omit whatever is still null.
 */
export const useSiteSettings = (): ComputedRef<SiteSettings> => {
    const page = usePage();

    return computed(() => page.props.siteSettings);
};

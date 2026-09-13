import type { Auth } from '@/types/auth';
import type { SiteSettings } from '@/types/site-settings';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** App\Models\ProductVariant::LOW_STOCK_THRESHOLD, shared on every response. */
            lowStockThreshold: number;
            /**
             * The single App\Models\SiteSetting row — storefront contact and
             * social details. Shared on every response, so the footer and nav
             * have it wherever they render; individual fields are null until
             * the operator sets them.
             */
            siteSettings: SiteSettings;
            /**
             * Orders placed but not yet looked at (unverified payment, pending
             * fulfillment) — the sidebar's attention badge. Shared on every
             * response, so it is current on whichever admin page is open.
             */
            pendingOrdersCount: number;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

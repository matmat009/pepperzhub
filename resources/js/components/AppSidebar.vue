<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    ChartNoAxesCombined,
    CreditCard,
    LayoutDashboard,
    MessageSquareQuote,
    Package,
    Plus,
    ShoppingCart,
    Tags,
    Truck,
} from '@lucide/vue';
import { IconSettings } from '@tabler/icons-vue';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavSecondary from '@/components/NavSecondary.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import {
    create as createProduct,
    index as productsIndex,
} from '@/routes/admin/products';
import { index as ordersIndex } from '@/routes/admin/orders';
import { index as paymentMethodsIndex } from '@/routes/admin/payment-methods';
import { index as reviewsIndex } from '@/routes/admin/reviews';
import { index as salesIndex } from '@/routes/admin/sales';
import { index as shippingCouriersIndex } from '@/routes/admin/shipping-couriers';
import { index as categoriesIndex } from '@/routes/admin/products/categories';
import { index as inventoryIndex } from '@/routes/admin/products/inventory';
import { edit as editProfile } from '@/routes/profile';
import type { NavMainItem } from '@/components/NavMain.vue';
import type { SidebarProps } from '@/components/ui/sidebar';

withDefaults(defineProps<SidebarProps>(), {
    collapsible: 'icon',
    variant: 'inset',
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const navMain: NavMainItem[] = [
    {
        title: 'Dashboard',
        url: dashboard(),
        icon: LayoutDashboard,
    },
];

/*
 * Read the same way as `name` and `auth.user` above: straight off the shared
 * props, typed in resources/js/types/global.d.ts. HandleInertiaRequests puts it
 * only on responses rendered inside this authenticated admin shell.
 */
const pendingOrdersCount = computed(() => page.props.pendingOrdersCount);

// Items without a `url` have no route yet and render as inert placeholders.
// Add the route helper to each one as its page lands.
//
// A computed rather than a plain array: the sidebar sits in the persistent
// layout, so its setup runs once and a count read into a literal here would
// stay frozen at whatever it was when the admin first landed. Recomputing off
// the shared prop is what lets verifying a payment drop the badge on the next
// Inertia visit rather than needing a full reload.
const navEcommerce = computed<NavMainItem[]>(() => [
    {
        title: 'Products',
        icon: Package,
        items: [
            // No icon was specified for All Products; it reuses the Products
            // icon so the three children stay aligned.
            { title: 'All Products', icon: Package, url: productsIndex() },
            { title: 'Add Product', icon: Plus, url: createProduct() },
            { title: 'Categories', icon: Tags, url: categoriesIndex() },
            { title: 'Inventory', icon: Boxes, url: inventoryIndex() },
        ],
    },
    {
        title: 'Orders',
        icon: ShoppingCart,
        url: ordersIndex(),
        badge: pendingOrdersCount.value,
    },
    /*
     * Top-level, beside Orders rather than under Products: it reports on
     * orders and revenue, and nothing on it is about the catalogue.
     */
    {
        title: 'Sales',
        icon: ChartNoAxesCombined,
        url: salesIndex(),
    },
    // Checkout's reference data, editable since Phase 3. Both were previously
    // seeder-only, so changing a rate or adding a method meant a redeploy.
    {
        title: 'Payments',
        icon: CreditCard,
        url: paymentMethodsIndex(),
    },
    {
        title: 'Shipping',
        icon: Truck,
        url: shippingCouriersIndex(),
    },
    // Storefront content rather than checkout reference data, but it sits with
    // them: it is the other thing the owner edits that customers see directly.
    {
        title: 'Reviews',
        icon: MessageSquareQuote,
        url: reviewsIndex(),
    },
]);

const navSecondary = [
    {
        title: 'Settings',
        url: editProfile(),
        icon: IconSettings,
    },
];
</script>

<template>
    <Sidebar :collapsible="collapsible" :variant="variant">
        <div class="admin-sidebar-wash flex h-full min-h-0 flex-col">
            <SidebarHeader class="gap-0 overflow-visible p-0">
                <Link
                    :href="home()"
                    aria-label="PepperzHub storefront"
                    class="flex min-h-[116px] w-full flex-col items-center justify-center overflow-visible rounded-md px-4 py-3 outline-none group-data-[collapsible=icon]:min-h-12 group-data-[collapsible=icon]:px-2 group-data-[collapsible=icon]:py-2 focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                >
                    <img
                        src="/images/branding/pepperzhub-navbar-logo.png"
                        alt=""
                        aria-hidden="true"
                        width="1763"
                        height="892"
                        class="h-auto w-[105px] object-contain group-data-[collapsible=icon]:w-8"
                    />
                    <span
                        class="mt-1 text-xl leading-none font-semibold whitespace-nowrap group-data-[collapsible=icon]:hidden"
                    >
                        <span class="text-sf-rose">Pepperz</span
                        ><span class="text-sf-primary">Hub</span>
                    </span>
                    <span
                        class="mt-1.5 pl-[0.35em] text-[8px] leading-none font-medium tracking-[0.35em] text-muted-foreground uppercase group-data-[collapsible=icon]:hidden"
                    >
                        Admin Panel
                    </span>
                </Link>
                <hr
                    aria-hidden="true"
                    class="mx-4 my-2 h-px border-0 bg-gradient-to-r from-sf-serenity-blue/30 via-sidebar-border to-sf-rose/25 group-data-[collapsible=icon]:hidden"
                />
            </SidebarHeader>
            <SidebarContent>
                <NavMain :items="navMain" />
                <NavMain label="E-commerce" :items="navEcommerce" />
                <NavSecondary :items="navSecondary" class="mt-auto" />
            </SidebarContent>
            <SidebarFooter>
                <NavUser :user="user" />
            </SidebarFooter>
        </div>
    </Sidebar>
</template>

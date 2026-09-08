<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CreditCard,
    LayoutDashboard,
    Package,
    Plus,
    ShoppingCart,
    Tags,
    Truck,
} from '@lucide/vue';
import { IconInnerShadowTop, IconSettings } from '@tabler/icons-vue';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavSecondary from '@/components/NavSecondary.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import {
    create as createProduct,
    index as productsIndex,
} from '@/routes/admin/products';
import { index as ordersIndex } from '@/routes/admin/orders';
import { index as paymentMethodsIndex } from '@/routes/admin/payment-methods';
import { index as shippingCouriersIndex } from '@/routes/admin/shipping-couriers';
import { index as categoriesIndex } from '@/routes/admin/products/categories';
import { edit as editProfile } from '@/routes/profile';
import type { NavMainItem } from '@/components/NavMain.vue';
import type { SidebarProps } from '@/components/ui/sidebar';

withDefaults(defineProps<SidebarProps>(), {
    collapsible: 'icon',
    variant: 'inset',
});

const page = usePage();
const appName = computed(() => page.props.name);
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
 * on every response.
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
            /*
             * Inventory is hidden, not deleted. Its screen still renders
             * hardcoded placeholder stock and its adjust action writes nothing,
             * while real stock lives on product_variants — showing it invites
             * someone to trust a number that is not real. The route and
             * controller stay put for the rebuild that follows Orders.
             */
            // { title: 'Inventory', icon: Boxes, url: inventoryIndex() },
        ],
    },
    {
        title: 'Orders',
        icon: ShoppingCart,
        url: ordersIndex(),
        badge: pendingOrdersCount.value,
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
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            class="data-[slot=sidebar-menu-button]:!p-1.5"
                        >
                            <Link :href="home()">
                                <IconInnerShadowTop class="!size-5" />
                                <span class="text-base font-semibold">{{
                                    appName
                                }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
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

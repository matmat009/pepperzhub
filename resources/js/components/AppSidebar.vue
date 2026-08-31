<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
// CreditCard is dropped along with the commented-out Payments entry below,
// matching how Boxes went with Inventory — an unused import fails lint.
import { LayoutDashboard, Package, ShoppingCart, Tags } from '@lucide/vue';
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
import { index as productsIndex } from '@/routes/admin/products';
import { index as ordersIndex } from '@/routes/admin/orders';
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

// Items without a `url` have no route yet and render as inert placeholders.
// Add the route helper to each one as its page lands.
const navEcommerce: NavMainItem[] = [
    {
        title: 'Products',
        icon: Package,
        items: [
            // No icon was specified for All Products; it reuses the Products
            // icon so the three children stay aligned.
            { title: 'All Products', icon: Package, url: productsIndex() },
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
    },
    /*
     * Payments is hidden, not deleted — same treatment as Inventory above. It
     * has no route yet, and a url-less item renders as a clickable href="#"
     * that goes nowhere, which reads as a broken link rather than a coming-soon
     * one. Restore it with the Phase 3 payments screen.
     */
    // { title: 'Payments', icon: CreditCard },
];

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
    </Sidebar>
</template>

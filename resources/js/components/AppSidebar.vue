<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    CreditCard,
    LayoutDashboard,
    Package,
    ShoppingCart,
    Tags,
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
import { index as productsIndex } from '@/routes/admin/products';
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
            { title: 'Categories', icon: Tags },
            { title: 'Inventory', icon: Boxes },
        ],
    },
    {
        title: 'Orders',
        icon: ShoppingCart,
    },
    {
        title: 'Payments',
        icon: CreditCard,
    },
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

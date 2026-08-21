<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    IconDashboard,
    IconDatabase,
    IconFileDescription,
    IconInnerShadowTop,
    IconReport,
    IconSettings,
} from '@tabler/icons-vue';
import { computed } from 'vue';
import NavDocuments from '@/components/NavDocuments.vue';
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
import { edit as editProfile } from '@/routes/profile';
import type { SidebarProps } from '@/components/ui/sidebar';

withDefaults(defineProps<SidebarProps>(), {
    collapsible: 'offcanvas',
    variant: 'inset',
});

const page = usePage();
const appName = computed(() => page.props.name);
const user = computed(() => page.props.auth.user);

const navMain = [
    {
        title: 'Dashboard',
        url: dashboard(),
        icon: IconDashboard,
    },
];

const navSecondary = [
    {
        title: 'Settings',
        url: editProfile(),
        icon: IconSettings,
    },
];

// Placeholder group from the dashboard-01 block. Swap these for real
// destinations once the Order / PaymentProof screens exist.
const documents = [
    {
        name: 'Data Library',
        url: '#',
        icon: IconDatabase,
    },
    {
        name: 'Reports',
        url: '#',
        icon: IconReport,
    },
    {
        name: 'Word Assistant',
        url: '#',
        icon: IconFileDescription,
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
            <NavDocuments :items="documents" />
            <NavSecondary :items="navSecondary" class="mt-auto" />
        </SidebarContent>
        <SidebarFooter>
            <NavUser :user="user" />
        </SidebarFooter>
    </Sidebar>
</template>

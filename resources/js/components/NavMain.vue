<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { IconCirclePlusFilled, IconMail } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

interface NavItem {
    title: string;
    url: NonNullable<InertiaLinkProps['href']>;
    icon?: Component;
}

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup>
        <SidebarGroupContent class="flex flex-col gap-2">
            <SidebarMenu>
                <SidebarMenuItem class="flex items-center gap-2">
                    <SidebarMenuButton
                        tooltip="Quick Create"
                        class="min-w-8 bg-primary text-primary-foreground duration-200 ease-linear hover:bg-primary/90 hover:text-primary-foreground active:bg-primary/90 active:text-primary-foreground"
                    >
                        <IconCirclePlusFilled />
                        <span>Quick Create</span>
                    </SidebarMenuButton>
                    <Button
                        size="icon"
                        class="size-8 group-data-[collapsible=icon]:opacity-0"
                        variant="outline"
                    >
                        <IconMail />
                        <span class="sr-only">Inbox</span>
                    </Button>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :tooltip="item.title"
                        :is-active="isCurrentUrl(item.url)"
                    >
                        <Link :href="item.url">
                            <component :is="item.icon" v-if="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>

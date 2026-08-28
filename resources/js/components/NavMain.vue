<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { Component } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

type Href = NonNullable<InertiaLinkProps['href']>;

/**
 * `url` is optional: items whose page does not exist yet render as inert
 * placeholders. Give them a route to turn them into real links.
 */
export type NavSubItem = {
    title: string;
    url?: Href;
    icon?: Component;
};

export type NavMainItem = {
    title: string;
    url?: Href;
    icon?: Component;
    items?: NavSubItem[];
};

const props = defineProps<{
    items: NavMainItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();

const isActive = (url?: Href) => (url ? isCurrentUrl(url) : false);

const hasActiveChild = (item: NavMainItem) =>
    item.items?.some((sub) => isActive(sub.url)) ?? false;
</script>

<template>
    <SidebarGroup>
        <SidebarGroupLabel v-if="props.label">
            {{ props.label }}
        </SidebarGroupLabel>
        <SidebarGroupContent>
            <SidebarMenu>
                <template v-for="item in items" :key="item.title">
                    <!-- Parent with children: collapsible sub-menu -->
                    <Collapsible
                        v-if="item.items?.length"
                        as-child
                        :default-open="hasActiveChild(item)"
                        class="group/collapsible"
                    >
                        <SidebarMenuItem>
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton :tooltip="item.title">
                                    <component
                                        :is="item.icon"
                                        v-if="item.icon"
                                    />
                                    <span>{{ item.title }}</span>
                                    <ChevronRight
                                        class="ml-auto transition-transform duration-200 ease-in-out group-data-[state=open]/collapsible:rotate-90 motion-reduce:transition-none"
                                    />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem
                                        v-for="sub in item.items"
                                        :key="sub.title"
                                    >
                                        <SidebarMenuSubButton
                                            as-child
                                            :is-active="isActive(sub.url)"
                                        >
                                            <Link
                                                v-if="sub.url"
                                                :href="sub.url"
                                            >
                                                <component
                                                    :is="sub.icon"
                                                    v-if="sub.icon"
                                                />
                                                <span>{{ sub.title }}</span>
                                            </Link>
                                            <a v-else href="#">
                                                <component
                                                    :is="sub.icon"
                                                    v-if="sub.icon"
                                                />
                                                <span>{{ sub.title }}</span>
                                            </a>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>

                    <!-- Leaf item -->
                    <SidebarMenuItem v-else>
                        <SidebarMenuButton
                            as-child
                            :tooltip="item.title"
                            :is-active="isActive(item.url)"
                        >
                            <Link v-if="item.url" :href="item.url">
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                            <a v-else href="#">
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </template>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>

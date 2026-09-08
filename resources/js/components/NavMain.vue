<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { Component } from 'vue';
import { Badge } from '@/components/ui/badge';
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

/**
 * `badge` is an attention count, rendered only when it is above zero — a badge
 * reading "0" is noise, and the absence of one is the same information. Honoured
 * on linked leaf items only: a parent's own row is a collapsible trigger, and an
 * item with no route is a placeholder for a page that does not exist, so neither
 * has a count to report.
 */
export type NavMainItem = {
    title: string;
    url?: Href;
    icon?: Component;
    items?: NavSubItem[];
    badge?: number;
};

const props = defineProps<{
    items: NavMainItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();

const isActive = (url?: Href) => (url ? isCurrentUrl(url) : false);

const hasActiveChild = (item: NavMainItem) =>
    item.items?.some((sub) => isActive(sub.url)) ?? false;

/*
 * Nav item weight: 500 inactive, 600 active. The vendored defaults are 400
 * inactive, and sub-items carry no weight rule at all — so a selected
 * sub-item was distinguished only by its pill, never by weight. Set here
 * rather than in components/ui/sidebar so shadcn stays regenerable; `cn()`
 * drops the base `data-[active=true]:font-medium` in favour of this.
 */
const navItemWeight = 'font-medium data-[active=true]:font-semibold';
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
                                <SidebarMenuButton
                                    :tooltip="item.title"
                                    :class="navItemWeight"
                                >
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
                                            :class="navItemWeight"
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
                            :class="navItemWeight"
                        >
                            <Link v-if="item.url" :href="item.url">
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                                <!--
                                    Hidden when the rail is collapsed to icons:
                                    the button is size-8 with overflow hidden
                                    there, so the pill would only be clipped.
                                -->
                                <Badge
                                    v-if="item.badge"
                                    class="ml-auto h-5 min-w-5 px-1.5 tabular-nums group-data-[collapsible=icon]:hidden"
                                >
                                    {{ item.badge }}
                                </Badge>
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

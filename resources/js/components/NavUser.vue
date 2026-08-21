<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    IconDotsVertical,
    IconLogout,
    IconPalette,
    IconShieldLock,
    IconUserCircle,
} from '@tabler/icons-vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useInitials } from '@/composables/useInitials';
import { logout } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { User } from '@/types';

const props = defineProps<{
    user: User;
}>();

const { isMobile } = useSidebar();
const { getInitials } = useInitials();

const showAvatar = computed(() => Boolean(props.user.avatar));

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <Avatar class="h-8 w-8 rounded-lg grayscale">
                            <AvatarImage
                                v-if="showAvatar"
                                :src="user.avatar!"
                                :alt="user.name"
                            />
                            <AvatarFallback class="rounded-lg">
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-medium">{{
                                user.name
                            }}</span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                                >{{ user.email }}</span
                            >
                        </div>
                        <IconDotsVertical class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="isMobile ? 'bottom' : 'right'"
                    :side-offset="4"
                    align="end"
                >
                    <DropdownMenuLabel class="p-0 font-normal">
                        <div
                            class="flex items-center gap-2 px-1 py-1.5 text-left text-sm"
                        >
                            <Avatar class="h-8 w-8 rounded-lg">
                                <AvatarImage
                                    v-if="showAvatar"
                                    :src="user.avatar!"
                                    :alt="user.name"
                                />
                                <AvatarFallback class="rounded-lg">
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div
                                class="grid flex-1 text-left text-sm leading-tight"
                            >
                                <span class="truncate font-medium">{{
                                    user.name
                                }}</span>
                                <span
                                    class="truncate text-xs text-muted-foreground"
                                    >{{ user.email }}</span
                                >
                            </div>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem as-child>
                            <Link
                                class="block w-full cursor-pointer"
                                :href="editProfile()"
                                prefetch
                            >
                                <IconUserCircle />
                                Profile
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link
                                class="block w-full cursor-pointer"
                                :href="editSecurity()"
                                prefetch
                            >
                                <IconShieldLock />
                                Security
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link
                                class="block w-full cursor-pointer"
                                :href="editAppearance()"
                                prefetch
                            >
                                <IconPalette />
                                Appearance
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link
                            class="block w-full cursor-pointer"
                            :href="logout()"
                            @click="handleLogout"
                            as="button"
                            data-test="logout-button"
                        >
                            <IconLogout />
                            Log out
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>

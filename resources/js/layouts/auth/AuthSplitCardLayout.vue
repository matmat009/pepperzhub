<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Card, CardContent } from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-muted p-6 md:p-10"
    >
        <div class="w-full max-w-sm md:max-w-4xl">
            <div class="flex flex-col gap-6">
                <Card class="overflow-hidden p-0">
                    <CardContent class="grid p-0 md:grid-cols-2">
                        <div class="flex flex-col gap-6 p-6 md:p-8">
                            <div
                                class="flex flex-col items-center gap-2 text-center"
                            >
                                <Link
                                    :href="home()"
                                    class="flex items-center justify-center"
                                >
                                    <AppLogoIcon
                                        class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                                    />
                                    <span class="sr-only">{{ title }}</span>
                                </Link>
                                <h1 class="text-2xl font-bold">{{ title }}</h1>
                                <p class="text-balance text-muted-foreground">
                                    {{ description }}
                                </p>
                            </div>

                            <slot />
                        </div>

                        <div class="relative hidden bg-muted md:block">
                            <img
                                src="/placeholder.svg"
                                alt=""
                                class="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>

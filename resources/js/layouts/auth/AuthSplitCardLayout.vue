<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <main
        class="flex min-h-screen min-h-svh w-full flex-col items-center justify-center bg-slate-50 px-4 py-4 text-sf-ink sm:px-6 sm:py-6 lg:px-8 lg:py-8 dark:bg-background dark:text-foreground [@media(max-height:799px)]:lg:py-5"
    >
        <div class="w-full max-w-[30rem] lg:max-w-[68rem]">
            <section
                class="grid overflow-hidden rounded-[1.25rem] border border-sf-serenity-blue/55 bg-card shadow-[0_24px_70px_-38px_rgba(49,82,133,0.45)] lg:grid-cols-[1.03fr_0.97fr] dark:border-sf-serenity-blue/30 dark:shadow-black/50"
                aria-labelledby="auth-heading"
            >
                <div
                    class="bg-card p-5 sm:p-7 lg:p-9 xl:p-10 [@media(max-height:799px)]:lg:py-7"
                >
                    <div class="mx-auto w-full max-w-[27rem]">
                        <Link
                            :href="home()"
                            aria-label="Go to the PepperzHub storefront"
                            class="flex w-fit items-center gap-2.5 rounded-md outline-none focus-visible:ring-3 focus-visible:ring-sf-primary/35 focus-visible:ring-offset-4 focus-visible:ring-offset-card"
                        >
                            <img
                                src="/images/branding/pepperzhub-emblem.png"
                                alt=""
                                class="h-9 w-auto object-contain sm:h-10"
                            />
                            <span
                                class="overflow-hidden rounded-sm bg-white ring-2 ring-white"
                            >
                                <img
                                    src="/images/branding/pepperzhub-wordmark.png"
                                    alt="PepperzHub"
                                    class="h-auto w-31 sm:w-34"
                                />
                            </span>
                        </Link>

                        <header class="mt-6 [@media(max-height:799px)]:lg:mt-5">
                            <p
                                class="text-[0.6875rem] font-semibold tracking-[0.3em] text-sf-primary-soft uppercase dark:text-sf-serenity-blue"
                            >
                                Admin Portal
                            </p>
                            <h1
                                id="auth-heading"
                                class="mt-2 text-[1.75rem] leading-[1.08] font-bold tracking-[-0.03em] text-slate-950 sm:text-[2rem] dark:text-white"
                            >
                                {{ title }}
                            </h1>
                            <p
                                class="mt-1.5 text-base text-slate-500 dark:text-muted-foreground"
                            >
                                {{ description }}
                            </p>
                        </header>

                        <div class="mt-6 [@media(max-height:799px)]:lg:mt-5">
                            <slot />
                        </div>
                    </div>
                </div>

                <aside
                    class="relative hidden overflow-hidden lg:block"
                    aria-label="PepperzHub brand message"
                >
                    <img
                        src="/images/branding/admin-login-artwork.png"
                        alt=""
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <div
                        class="absolute inset-0 hidden bg-slate-950/35 dark:block"
                    />

                    <div
                        class="relative grid h-full grid-rows-[minmax(0,1fr)_auto] p-9 xl:p-10 [@media(max-height:799px)]:py-7"
                    >
                        <div class="flex min-h-0 items-center justify-center">
                            <img
                                src="/images/branding/pepperzhub-lockup.png"
                                alt="PepperzHub Peptide Solutions"
                                class="h-auto w-[68%] max-w-[19rem] drop-shadow-[0_8px_20px_rgba(255,255,255,0.35)]"
                            />
                        </div>

                        <div class="pt-6 [@media(max-height:799px)]:pt-4">
                            <p
                                class="max-w-[24rem] text-[1.75rem] leading-[1.15] font-medium tracking-[-0.03em] text-slate-950 xl:text-[2rem] dark:text-white"
                            >
                                A little clarity.<br />
                                A better workday.
                            </p>
                            <p
                                class="mt-2.5 text-base text-slate-600 dark:text-blue-100"
                            >
                                Your store, thoughtfully managed.
                            </p>
                        </div>
                    </div>
                </aside>
            </section>

            <p
                class="mt-4 text-center text-sm text-slate-500 dark:text-muted-foreground"
            >
                PepperzHub · Admin portal
            </p>
        </div>
    </main>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Welcome back',
        description: 'Sign in to manage your store.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        role="status"
        class="mb-4 rounded-lg border border-emerald-600/20 bg-emerald-50 px-4 py-2.5 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
    >
        {{ status }}
    </div>

    <!-- @chisel-passkeys -->
    <PasskeyVerify
        class="[&_.my-6]:my-4 [&_.uppercase]:normal-case [&_[data-slot=button]]:h-12 [&_[data-slot=button]]:rounded-lg [&_[data-slot=button]]:border-sf-serenity-blue/75 [&_[data-slot=button]]:text-sm [&_[data-slot=button]]:font-semibold [&_[data-slot=button]]:shadow-none [&_[data-slot=button]]:hover:border-sf-primary/60 [&_[data-slot=button]]:hover:bg-blue-50/65 dark:[&_[data-slot=button]]:hover:bg-sf-primary/10 [&_[data-slot=separator]]:bg-sf-serenity-blue/50"
    />
    <!-- @end-chisel-passkeys -->

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
    >
        <FieldGroup class="gap-[1.125rem]">
            <Field class="gap-2">
                <FieldLabel
                    for="email"
                    class="text-sm font-medium text-slate-700 dark:text-foreground"
                >
                    Email address
                </FieldLabel>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                    :aria-invalid="Boolean(errors.email)"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                    class="h-12 rounded-lg border-sf-serenity-blue/75 px-4 text-base shadow-none placeholder:text-slate-400 focus-visible:border-sf-primary focus-visible:ring-sf-primary/20 dark:border-sf-serenity-blue/45 dark:bg-white/[0.04]"
                />
                <div id="email-error" aria-live="polite">
                    <InputError :message="errors.email" />
                </div>
            </Field>

            <Field class="gap-2">
                <div class="grid grid-cols-[1fr_auto] items-center gap-x-4">
                    <FieldLabel
                        for="password"
                        class="text-sm font-medium text-slate-700 dark:text-foreground"
                    >
                        Password
                    </FieldLabel>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        :aria-invalid="Boolean(errors.password)"
                        :aria-describedby="
                            errors.password ? 'password-error' : undefined
                        "
                        wrapper-class="col-span-2 mt-2"
                        class="h-12 rounded-lg border-sf-serenity-blue/75 px-4 pr-12 text-base shadow-none placeholder:text-slate-400 focus-visible:border-sf-primary focus-visible:ring-sf-primary/20 dark:border-sf-serenity-blue/45 dark:bg-white/[0.04]"
                    />
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="col-start-2 row-start-1 text-sm font-medium text-blue-600 no-underline hover:text-blue-700 hover:underline focus-visible:rounded-sm focus-visible:ring-3 focus-visible:ring-sf-primary/25 focus-visible:outline-none dark:text-blue-300 dark:hover:text-blue-200"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <div id="password-error" aria-live="polite">
                    <InputError :message="errors.password" />
                </div>
            </Field>

            <Field orientation="horizontal" class="pt-0.5">
                <FieldLabel
                    for="remember"
                    class="items-center gap-2.5 text-sm text-slate-600 dark:text-muted-foreground"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        class="size-[1.125rem] border-sf-serenity-blue/75 focus-visible:ring-sf-primary/25 data-[state=checked]:border-sf-primary data-[state=checked]:bg-sf-primary"
                    />
                    <span class="font-normal">Remember me</span>
                </FieldLabel>
            </Field>

            <Field class="pt-0.5">
                <Button
                    type="submit"
                    :disabled="processing"
                    :aria-busy="processing"
                    data-test="login-button"
                    class="h-12 w-full rounded-lg border-sf-primary bg-sf-primary text-base font-semibold text-white shadow-[0_7px_18px_-12px_rgba(39,92,168,0.75)] hover:border-sf-primary-hover hover:bg-sf-primary-hover focus-visible:ring-sf-primary/35 dark:text-white"
                >
                    <Spinner v-if="processing" />
                    <span>{{ processing ? 'Logging in…' : 'Log in' }}</span>
                    <template v-if="!processing">
                        <ArrowRight class="ml-1 size-5" />
                    </template>
                </Button>
            </Field>

            <!-- @chisel-registration -->
            <!--
                Sign-up link removed with Fortify's registration feature: the
                `register` route no longer exists, so Wayfinder does not emit a
                helper for it and this block would not compile.
            -->
            <!-- @end-chisel-registration -->
        </FieldGroup>
    </Form>
</template>

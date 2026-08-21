<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @end-chisel-passkeys */

defineOptions({
    layout: {
        title: 'Welcome back',
        description: 'Enter your email and password below to log in',
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
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <!-- @chisel-passkeys -->
    <!-- PasskeyVerify ends in its own separator with a bottom margin; cancel
         the layout's column gap so it doesn't double up before the form. -->
    <div class="-mb-6">
        <PasskeyVerify />
    </div>
    <!-- @end-chisel-passkeys -->

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
    >
        <FieldGroup>
            <Field>
                <FieldLabel for="email">Email address</FieldLabel>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </Field>

            <Field>
                <div class="flex items-center">
                    <FieldLabel for="password">Password</FieldLabel>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="ml-auto text-sm underline-offset-2 hover:underline"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </Field>

            <Field orientation="horizontal">
                <FieldLabel for="remember" class="items-center gap-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span class="font-normal">Remember me</span>
                </FieldLabel>
            </Field>

            <Field>
                <Button
                    type="submit"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Log in
                </Button>
            </Field>

            <!-- @chisel-registration -->
            <FieldDescription class="text-center">
                Don't have an account?
                <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
            </FieldDescription>
            <!-- @end-chisel-registration -->
        </FieldGroup>
    </Form>
</template>

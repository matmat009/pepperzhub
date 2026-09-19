<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
/* @chisel-email-verification */
import { Link } from '@inertiajs/vue3';
/* @end-chisel-email-verification */
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SiteSettingController from '@/actions/App/Http/Controllers/Settings/SiteSettingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSiteSettings } from '@/composables/useSiteSettings';
import { edit } from '@/routes/profile';
/* @chisel-email-verification */
import { send } from '@/routes/verification';
/* @end-chisel-email-verification */

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

/*
 * The storefront's own details, not the operator's. Its form below submits to
 * its own route and shares nothing with the account fields above it: saving one
 * must never resubmit the other's values.
 */
const siteSettings = useSiteSettings();
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    :model-value="user.email"
                    disabled
                    readonly
                />
                <InputError class="mt-2" :message="errors.email" />
                <p class="text-sm text-muted-foreground">
                    The administrator email is managed by the system and cannot
                    be changed here.
                </p>
            </div>

            <!-- @chisel-email-verification -->
            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>
            <!-- @end-chisel-email-verification -->

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Storefront Contact Info"
            description="Shown in the storefront footer. Leave anything you do not have yet blank and it is left out rather than rendered empty."
        />

        <!--
            Its own Form, deliberately. The two forms on this page are
            independent submissions to independent routes, so saving these
            cannot touch the account fields above and vice versa.
        -->
        <Form
            v-bind="SiteSettingController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="contact_email">Contact email</Label>
                <Input
                    id="contact_email"
                    type="email"
                    class="mt-1 block w-full"
                    name="contact_email"
                    :default-value="siteSettings.contact_email ?? ''"
                    placeholder="support@pepperzhub.ph"
                />
                <InputError class="mt-2" :message="errors.contact_email" />
            </div>

            <div class="grid gap-2">
                <Label for="contact_phone">Contact phone</Label>
                <Input
                    id="contact_phone"
                    class="mt-1 block w-full"
                    name="contact_phone"
                    :default-value="siteSettings.contact_phone ?? ''"
                    placeholder="0917 123 4567"
                />
                <InputError class="mt-2" :message="errors.contact_phone" />
            </div>

            <div class="grid gap-2">
                <Label for="contact_address">Contact address</Label>
                <Input
                    id="contact_address"
                    class="mt-1 block w-full"
                    name="contact_address"
                    :default-value="siteSettings.contact_address ?? ''"
                    placeholder="Metro Manila, Philippines"
                />
                <InputError class="mt-2" :message="errors.contact_address" />
            </div>

            <div class="grid gap-2">
                <Label for="facebook_url">Facebook URL</Label>
                <Input
                    id="facebook_url"
                    type="url"
                    class="mt-1 block w-full"
                    name="facebook_url"
                    :default-value="siteSettings.facebook_url ?? ''"
                    placeholder="https://facebook.com/pepperzhub"
                />
                <p class="text-sm text-muted-foreground">
                    Also used for the Messenger contact option on the storefront
                    FAQ page.
                </p>
                <InputError class="mt-2" :message="errors.facebook_url" />
            </div>

            <div class="grid gap-2">
                <Label for="instagram_url">Instagram URL</Label>
                <Input
                    id="instagram_url"
                    type="url"
                    class="mt-1 block w-full"
                    name="instagram_url"
                    :default-value="siteSettings.instagram_url ?? ''"
                    placeholder="https://instagram.com/pepperzhub"
                />
                <InputError class="mt-2" :message="errors.instagram_url" />
            </div>

            <div class="grid gap-2">
                <Label for="tiktok_url">TikTok URL</Label>
                <Input
                    id="tiktok_url"
                    type="url"
                    class="mt-1 block w-full"
                    name="tiktok_url"
                    :default-value="siteSettings.tiktok_url ?? ''"
                    placeholder="https://tiktok.com/@pepperzhub"
                />
                <InputError class="mt-2" :message="errors.tiktok_url" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-site-settings-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>
</template>

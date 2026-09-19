<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { LockKeyhole, ShieldCheck } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    twoFactorAvailable?: boolean;
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    twoFactorSettingsLocked?: boolean;
    twoFactorStatus?: 'enabled' | 'disabled' | 'pending';
};

const props = withDefaults(defineProps<Props>(), {
    twoFactorAvailable: false,
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
    twoFactorSettingsLocked: true,
    twoFactorStatus: 'disabled',
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);
const statusLabel = computed(() => {
    if (props.twoFactorStatus === 'enabled') {
        return 'Enabled';
    }

    if (props.twoFactorStatus === 'pending') {
        return 'Pending confirmation';
    }

    return 'Disabled';
});

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <div v-if="twoFactorAvailable" class="space-y-6">
        <Heading
            variant="small"
            title="Two-factor authentication"
            description="Review your two-factor authentication status"
        />

        <div v-if="twoFactorSettingsLocked" class="space-y-4">
            <div class="flex items-center gap-2 text-sm">
                <span class="font-medium">Current status</span>
                <Badge variant="outline" data-test="two-factor-status">
                    {{ statusLabel }}
                </Badge>
            </div>

            <div
                class="flex gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
                role="status"
            >
                <LockKeyhole
                    class="mt-0.5 size-4 shrink-0 text-foreground"
                    aria-hidden="true"
                />
                <p>
                    Two-factor authentication is locked and managed by the
                    authorized system maintainer.
                </p>
            </div>

            <div v-if="twoFactorStatus === 'disabled'">
                <Button
                    type="button"
                    disabled
                    aria-disabled="true"
                    tabindex="-1"
                    data-test="enable-two-factor-disabled"
                >
                    Enable 2FA
                </Button>
            </div>

            <div
                v-else-if="twoFactorStatus === 'pending'"
                class="flex flex-wrap gap-3"
            >
                <Button
                    type="button"
                    disabled
                    aria-disabled="true"
                    tabindex="-1"
                    data-test="confirm-two-factor-disabled"
                >
                    <ShieldCheck />Continue setup
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled
                    aria-disabled="true"
                    tabindex="-1"
                    data-test="restart-two-factor-disabled"
                >
                    Restart setup
                </Button>
            </div>

            <div v-else class="flex flex-wrap gap-3">
                <Button
                    type="button"
                    variant="destructive"
                    disabled
                    aria-disabled="true"
                    tabindex="-1"
                    data-test="disable-two-factor-disabled"
                >
                    Disable 2FA
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled
                    aria-disabled="true"
                    tabindex="-1"
                    data-test="view-recovery-codes-disabled"
                >
                    View recovery codes
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    disabled
                    aria-disabled="true"
                    tabindex="-1"
                    data-test="regenerate-recovery-codes-disabled"
                >
                    Regenerate recovery codes
                </Button>
            </div>
        </div>

        <template v-else-if="canManageTwoFactor">
            <div
                v-if="!twoFactorEnabled"
                class="flex flex-col items-start justify-start space-y-4"
            >
                <p class="text-sm text-muted-foreground">
                    When you enable two-factor authentication, you will be
                    prompted for a secure pin during login. This pin can be
                    retrieved from a TOTP-supported application on your phone.
                </p>

                <div>
                    <Button v-if="hasSetupData" @click="showSetupModal = true">
                        <ShieldCheck />Continue setup
                    </Button>
                    <Form
                        v-else
                        v-bind="enable.form()"
                        @success="showSetupModal = true"
                        #default="{ processing }"
                    >
                        <Button type="submit" :disabled="processing">
                            Enable 2FA
                        </Button>
                    </Form>
                </div>
            </div>

            <div
                v-else
                class="flex flex-col items-start justify-start space-y-4"
            >
                <p class="text-sm text-muted-foreground">
                    You will be prompted for a secure, random pin during login,
                    which you can retrieve from the TOTP-supported application
                    on your phone.
                </p>

                <div class="relative inline">
                    <Form v-bind="disable.form()" #default="{ processing }">
                        <Button
                            variant="destructive"
                            type="submit"
                            :disabled="processing"
                        >
                            Disable 2FA
                        </Button>
                    </Form>
                </div>

                <TwoFactorRecoveryCodes />
            </div>

            <TwoFactorSetupModal
                v-model:isOpen="showSetupModal"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </template>
    </div>
</template>

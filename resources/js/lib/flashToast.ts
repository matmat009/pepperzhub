import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { ToasterProps } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

/**
 * One tone per toast type, borrowed from the status badges on the Orders table
 * so a toast and the row it just changed report the same outcome in the same
 * colour.
 *
 * The `!` suffixes are load-bearing. vue-sonner paints the toast from
 * `[data-sonner-toast][data-styled='true']` — two attribute selectors, which a
 * plain utility class cannot outrank on specificity alone.
 */
const tone = {
    success:
        'border-emerald-200! bg-emerald-50! text-emerald-700! dark:border-emerald-400/30! dark:bg-emerald-950! dark:text-emerald-200!',
    error: 'border-red-200! bg-red-50! text-red-700! dark:border-red-400/30! dark:bg-red-950! dark:text-red-200!',
    warning:
        'border-amber-200! bg-amber-50! text-amber-700! dark:border-amber-400/30! dark:bg-amber-950! dark:text-amber-200!',
    info: 'border-blue-200! bg-blue-50! text-blue-700! dark:border-blue-400/30! dark:bg-blue-950! dark:text-blue-200!',
};

/**
 * Shared so the two layouts that mount <Toaster> cannot drift apart on
 * position or colour.
 */
export const flashToasterProps = {
    position: 'top-right',
    toastOptions: { classes: tone },
} satisfies ToasterProps;

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });
}

import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type ThemeScope = 'admin' | 'forced-light';

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const activeThemeScope = ref<ThemeScope>('admin');

const applyResolvedTheme = (value: ResolvedAppearance): void => {
    document.documentElement.classList.toggle('dark', value === 'dark');
    document.documentElement.style.colorScheme = value;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (activeThemeScope.value === 'forced-light') {
        applyResolvedTheme('light');

        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        applyResolvedTheme(systemTheme);
    } else {
        applyResolvedTheme(value);
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function applyThemeScope(scope: ThemeScope): void {
    if (typeof window === 'undefined') {
        return;
    }

    activeThemeScope.value = scope;
    document.documentElement.dataset.themeScope = scope;

    if (scope === 'forced-light') {
        applyResolvedTheme('light');

        return;
    }

    updateTheme(getStoredAppearance() || 'system');
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const initialScope: ThemeScope =
        document.documentElement.dataset.themeScope === 'forced-light'
            ? 'forced-light'
            : 'admin';

    applyThemeScope(initialScope);

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (activeThemeScope.value === 'forced-light') {
            return 'light';
        }

        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        if (activeThemeScope.value === 'forced-light') {
            return;
        }

        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}

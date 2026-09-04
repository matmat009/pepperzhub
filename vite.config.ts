import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                // Storefront: Lora throughout, matching the design artboards.
                // Headings and wordmark sit at 500-600, body copy at 400, with
                // italics carrying the supporting lines.
                bunny('Lora', {
                    weights: [400, 500, 600],
                    styles: ['normal', 'italic'],
                }),
                // Admin: Geist throughout, applied via `font-sans`.
                // 700 is loaded for table headers and primary buttons.
                bunny('Geist', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    /* W240 MIXXTUNE_FEWER_FILES: every icon used to be its own tiny file (one request each); icons and the base libraries now travel as two files */
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/lucide-react')) return 'icons';
                    if (/node_modules\/(react|react-dom|scheduler|@inertiajs|axios|lodash[^/]*|qs|nprogress)\//.test(id)) return 'vendor';
                },
            },
        },
    },
});

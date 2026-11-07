import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // Public website and admin portal are separate bundles; visitors never download admin code.
            input: ['resources/css/app.css', 'resources/js/main.jsx', 'resources/css/admin.css', 'resources/js/admin/main.jsx', 'resources/js/portal/main.jsx', 'resources/css/document.css'],
            refresh: ['resources/views/**', 'resources/content/**', 'routes/**'],
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@content': fileURLToPath(new URL('./resources/content', import.meta.url)),
        },
    },
    build: {
        sourcemap: false,
        // The lazily loaded Three.js scene is ~570 KB raw (~145 KB gzip) and never blocks first paint.
        chunkSizeWarningLimit: 600,
    },
    server: {
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

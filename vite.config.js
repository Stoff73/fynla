import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    // Base path is configurable via VITE_BASE_PATH environment variable
    // Development: '/' (default)
    // Production fynla.org (root): '/build/'
    // Production csjones.co/fynla (subdirectory): '/fynla/build/'
    base: process.env.VITE_BASE_PATH || '/',
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            buildDirectory: 'build',
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    build: {
        sourcemap: false,
        manifest: 'manifest.json', // Place manifest.json at build root, not .vite subdirectory
        outDir: 'public/build',
        rollupOptions: {
            input: {
                app: 'resources/js/app.js',
                css: 'resources/css/app.css',
            },
        },
    },
});

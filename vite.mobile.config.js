import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

// Isolated mobile build. Deliberately NO laravel-vite-plugin: that writes a
// `public/hot` file and resolves assets via the dev server, which would couple
// this build to the main web dev server. The /m/app Blade reads
// public/m-build/manifest.json directly instead (see resources/views/mobile-app.blade.php).
//
// iOS-safety rules inherited verbatim from CLAUDE.md (Capacitor loads this build):
//   - NO `external` for image/asset paths in rollupOptions
//   - transformAssetUrls: false in the vue() plugin
//   - no PWA / service worker in this build
export default defineConfig({
    base: process.env.VITE_MOBILE_BASE_PATH || '/m-build/',
    plugins: [
        vue({
            template: { transformAssetUrls: false },
        }),
    ],
    resolve: {
        alias: { '@m': path.resolve(__dirname, 'resources/mobile') },
    },
    build: {
        sourcemap: false,
        manifest: 'manifest.json',
        outDir: 'public/m-build',
        emptyOutDir: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/mobile/main.js'),
        },
    },
});

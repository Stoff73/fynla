import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';
import path from 'path';
import fs from 'fs';

// Revolut embeddedCheckout requires a real domain (not localhost/IP).
// Set VITE_LOCAL_DOMAIN=true to serve on local.fynla.org with HTTPS instead of 127.0.0.1
// Requires: /etc/hosts entry (127.0.0.1 local.fynla.org) and mkcert certs in .certs/
const useLocalDomain = process.env.VITE_LOCAL_DOMAIN === 'true';
const disablePWA = process.env.VITE_DISABLE_PWA === 'true';
const localCertPath = path.resolve(__dirname, '.certs/local.fynla.org.pem');
const localKeyPath = path.resolve(__dirname, '.certs/local.fynla.org-key.pem');
const hasLocalCerts = fs.existsSync(localCertPath) && fs.existsSync(localKeyPath);

export default defineConfig({
    // Base path is configurable via VITE_BASE_PATH environment variable
    // Development: '/' (default)
    // Production fynla.org (root): '/build/'
    // Production csjones.co/fynla (subdirectory): '/fynla/build/'
    base: process.env.VITE_BASE_PATH || '/',
    server: {
        host: useLocalDomain ? 'local.fynla.org' : '127.0.0.1',
        port: 5174,
        strictPort: true,
        cors: true,
        ...(useLocalDomain && hasLocalCerts ? {
            https: {
                cert: fs.readFileSync(localCertPath),
                key: fs.readFileSync(localKeyPath),
            },
        } : {}),
        // In local domain mode, proxy all non-asset requests to Laravel
        // so everything is on the same origin (no cross-port issues).
        // Navigate to https://local.fynla.org:5173 — Vite serves its own assets
        // and proxies everything else to Laravel on :8000.
        ...(useLocalDomain ? {
            proxy: {
                // Catch-all: proxy everything to Laravel.
                // Vite handles its own paths (/@vite, /resources, /node_modules, etc.)
                // BEFORE the proxy runs, so they won't be proxied.
                '^/(?!(resources/|@|node_modules/|__vite))': {
                    target: 'http://127.0.0.1:8000',
                    changeOrigin: true,
                },
            },
        } : {}),
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            buildDirectory: 'build',
        }),
        vue({
            template: {
                // Disable asset URL transformation so <img src="/images/..."> stays
                // as a static string instead of becoming a JS import. Without this,
                // Capacitor iOS WKWebView rejects the import with 'image/png is not
                // a valid JavaScript MIME type' because it serves the PNG file.
                transformAssetUrls: false,
            },
        }),
        !disablePWA && VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico'],
            manifest: {
                name: 'Fynla — Your Financial Companion',
                short_name: 'Fynla',
                description: 'UK financial planning made simple',
                theme_color: '#1F2A44',
                background_color: '#F7F6F4',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/dashboard',
                categories: ['finance', 'lifestyle'],
                icons: [
                    { src: '/icons/icon-72x72.png', sizes: '72x72', type: 'image/png' },
                    { src: '/icons/icon-96x96.png', sizes: '96x96', type: 'image/png' },
                    { src: '/icons/icon-128x128.png', sizes: '128x128', type: 'image/png' },
                    { src: '/icons/icon-144x144.png', sizes: '144x144', type: 'image/png' },
                    { src: '/icons/icon-152x152.png', sizes: '152x152', type: 'image/png' },
                    { src: '/icons/icon-192x192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
                    { src: '/icons/icon-384x384.png', sizes: '384x384', type: 'image/png' },
                    { src: '/icons/icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
                ],
                shortcuts: [
                    {
                        name: 'Ask Fyn',
                        url: '/dashboard',
                        description: 'Open Fyn chat assistant',
                    },
                    {
                        name: 'Goals',
                        url: '/goals',
                        description: 'View your financial goals',
                    },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
                navigateFallback: null,
                runtimeCaching: [
                    // Google Fonts — CacheFirst (rarely change)
                    {
                        urlPattern: /^https:\/\/fonts\.googleapis\.com/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'google-fonts-stylesheets',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.gstatic\.com/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'google-fonts-webfonts',
                            expiration: {
                                maxEntries: 20,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    // Plausible analytics — NetworkOnly (must always reach server)
                    {
                        urlPattern: /^https:\/\/plausible\.io/,
                        handler: 'NetworkOnly',
                    },
                    // API dashboard & module endpoints — NetworkFirst (show cached on offline)
                    {
                        urlPattern: /\/api\/v1\/mobile\/(dashboard|modules|insights)/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'api-mobile-data',
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            networkTimeoutSeconds: 10,
                        },
                    },
                    // AI chat & SSE streams — NetworkOnly (cannot cache streamed responses)
                    {
                        urlPattern: /\/api\/(ai-chat|chat)/,
                        handler: 'NetworkOnly',
                    },
                    // General API — NetworkFirst with short cache
                    {
                        urlPattern: /\/api\//,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'api-general',
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 5,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            networkTimeoutSeconds: 10,
                        },
                    },
                    // Images — CacheFirst
                    {
                        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp|ico)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'images',
                            expiration: {
                                maxEntries: 60,
                                maxAgeSeconds: 60 * 60 * 24 * 30,
                            },
                        },
                    },
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
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

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import fs from 'fs';

// Revolut embeddedCheckout requires a real domain (not localhost/IP).
// Set VITE_LOCAL_DOMAIN=true to serve on local.fynla.org with HTTPS instead of 127.0.0.1
// Requires: /etc/hosts entry (127.0.0.1 local.fynla.org) and mkcert certs in .certs/
const useLocalDomain = process.env.VITE_LOCAL_DOMAIN === 'true';
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
        port: 5173,
        strictPort: true,
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

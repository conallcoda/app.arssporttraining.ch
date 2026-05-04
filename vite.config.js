import { resolve } from 'node:path';
import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    resolve: {
        alias: {
            treeselectjs: resolve(__dirname, 'node_modules/treeselectjs'),
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        fs: {
            allow: [
                __dirname,
                '/Users/conalloreilly/Development/coda-packages',
            ],
        },
    },
});

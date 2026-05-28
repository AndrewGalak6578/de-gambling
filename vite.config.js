import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        port: Number(env.VITE_PORT || 5176),
        strictPort: true,
        origin: env.VITE_DEV_SERVER_URL || `http://localhost:${env.VITE_PORT || 5176}`,
        cors: {
            origin: env.APP_URL || true,
        },
        hmr: {
            host: new URL(env.VITE_DEV_SERVER_URL || `http://localhost:${env.VITE_PORT || 5176}`).hostname,
            clientPort: Number(env.VITE_PORT || 5176),
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    };
});

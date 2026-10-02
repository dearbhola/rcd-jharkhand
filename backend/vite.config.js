import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/gis/map-dashboard.js',
                'resources/js/gis/geometry-editor.js',
                'resources/js/gis/locate.js',
                'resources/js/reports/create.js',
                'resources/js/reports/show-map.js',
                'resources/js/reports/actions.js',
                'resources/js/dashboard/charts.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

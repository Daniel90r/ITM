import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '127.0.0.1',
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/notas.js',
                'resources/js/admin/alumnos.js',
                'resources/js/admin/mensualidades.js',
                'resources/js/admin/cursos.js',
                'resources/js/admin/solicitudes.js',
                'resources/js/admin/materias.js',
                'resources/js/admin/pagos.js',
                'resources/js/admin/boletas.js',
                'resources/js/admin/maestros.js',
                'resources/js/admin/modals.js',
                'resources/js/admin/inscripcion.js',
                'resources/js/docente/notas.js',
                'resources/js/docente/asistencia.js',
                'resources/js/alumno/inscripcion.js'
            ],
            refresh: true,
        }),
    ],
});

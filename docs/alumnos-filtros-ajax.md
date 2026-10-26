# Filtros dinámicos de Gestión de Alumnos

La navegación completa procedía de `requestSubmit()` en Curso e Históricos y
`location.assign()` en Limpiar. Año y búsqueda usaban un filtrado local distinto
del GET de Laravel.

La mejora progresiva usa el mismo GET `/admin/alumnos` y su HTML autorizado.
No cambia controladores, consultas ni cálculos. Sustituye exclusivamente los
fragmentos marcados de tabla, estadísticas, contador y opciones de curso/año.
El formulario, el foco de búsqueda, el menú, el encabezado y los modales se
conservan. Los botones de cada fila siguen usando sus funciones/rutas existentes.

`alumnos-filtros.js` retira una sola vez los manejadores inline de los filtros
antes de instalar los eventos delegados. La utilidad de consultas GET sigue el
patrón de Asistencias, sin cambiar ese módulo ni añadir dependencias. La búsqueda
espera 300 ms y una nueva pulsación invalida inmediatamente cualquier respuesta
anterior. Se comprueba la revisión además de utilizar AbortController.

La URL se actualiza solo después de una respuesta válida. Atrás/Adelante vuelve
a consultar sin crear nuevas entradas. Una selección histórica se normaliza con
la respuesta de Laravel. Fallos, validaciones y sesiones vencidas conservan los
resultados anteriores y muestran un mensaje. El formulario GET y las opciones
noscript permanecen como alternativa.

El listado actual no tiene paginación: el controlador utiliza `get()`. Se incluye
delegación de enlaces de paginación dentro de resultados para compatibilidad,
comprobada con la prueba JavaScript; no se añadió paginación ni se cambió la consulta.

## Archivos

- `resources/views/admin/alumnos.blade.php`
- `resources/js/admin/alumnos.js`
- `resources/js/admin/alumnos-filtros.js` (nuevo)
- `resources/js/utils/consulta-dinamica.js` (nuevo)
- `tests/js/alumnos-filtros.test.mjs` (nuevo)
- `tests/Feature/AlumnosFiltrosPresentacionTest.php`
- `tests/Frontend/alumnos-filtros-presentacion.cjs`
- `docs/alumnos-filtros-ajax.md` (nuevo)

## Verificación aislada

Las pruebas PHP exigen SQLite `:memory:` y almacenamiento temporal. Se verificó
la respuesta AJAX contra los mismos datos del GET tradicional. No se ejecutaron
pruebas contra MySQL real ni TransicionDocumentalTest.php.

`node tests/js/alumnos-filtros.test.mjs` comprueba debounce, concurrencia, errores,
historial, limpieza, curso y conservación de parámetros.

La prueba Chrome requiere primero exportar las vistas de
`AlumnosFiltrosPresentacionTest` mediante `ITM_ALUMNOS_FILTROS_PREVIEW_DIR` a un
directorio temporal privado. Después se ejecuta
`node tests/Frontend/alumnos-filtros-presentacion.cjs`. El script inicia y cierra
su propio servidor de HTML sintético y navegador; no inicia Laravel ni accede a
MySQL. Cubre 320, 375, 390, 768 y 1440 px, actualizaciones de estadísticas, foco,
limpieza, historial, Editar, enlace de Estado y acceso y errores de red/servidor.
Las capturas quedan en ese directorio temporal.

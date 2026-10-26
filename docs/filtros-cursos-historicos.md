# Cursos activos e históricos en consultas

El catálogo de lectura se obtiene con `CursosConsulta`. Evalúa el accesor
`Curso::estado_curso` para cada curso; no sustituye esa regla por `activo`.
La visibilidad y el estado académico son independientes.

- Por defecto se consultan cursos ACTIVO.
- `incluir_historicos=1` incorpora también FINALIZADOS, incluidos los cerrados
  manualmente. No incorpora PRÓXIMOS ni SIN_FECHAS.
- Una URL con un `curso_id` finalizado activa automáticamente el histórico.
- Un curso próximo seleccionado explícitamente en una consulta produce un
  error de validación; nunca se interpreta como «todos».
- Desmarcar la opción conserva el curso y la materia si el curso sigue activo.
  Si estaba seleccionado un curso finalizado, limpia ambos antes de consultar.
- El alcance se conserva en paginación, sugerencias, respuestas AJAX y enlaces
  al visor y PDF. Las consultas de tabla y PDF comparten los mismos criterios.
- El estado mostrado es el estado académico actual; no reconstruye estados
  históricos a partir del año consultado.

## Pantallas

Alumnos, Alumnos inscritos, Registro de Notas, Asistencias, consulta de cuotas
en Mensualidades, Boletas y los seis listados de Reportes. En Alumnos se
conservan también expedientes sin inscripción aprobada cuando no se selecciona
un curso. Sus filtros locales de nombre y año siguen disponibles.

En las consultas de Notas y Asistencias del docente, las opciones se intersectan
con sus asignaciones autorizadas. Este cambio no concede acceso ni cambia las
reglas de captura. El selector de materia sigue dependiendo del curso.

## Excepciones conservadas

- Nuevas inscripciones: continúan usando el catálogo PRÓXIMO.
- Asignaciones docentes: conservan cursos próximos y programaciones.
- Generación de mensualidades: conserva su catálogo y elegibilidad anteriores;
  `cursosFiltro` es independiente del catálogo operativo `$cursos`.
- Gestión del catálogo de Cursos y Materias: no son filtros de selección de un
  curso académico; siguen mostrando los registros administrables.
- Solicitudes y su PDF: no disponen de filtro de curso. Se conservan pendientes,
  rechazadas y otras solicitudes, incluidas las de cursos próximos.
- Pagos y estado de cuenta: no tenían selector de curso. Se conserva íntegro su
  alcance económico, incluidas obligaciones sin curso asociado.
- Las rutas directas de boletas y sus autorizaciones permanecen vigentes.

## Validación aislada

`CursosConsultaTest` comprueba el estado canónico, visibilidad independiente,
históricos por fecha y manuales, URLs anteriores, permisos docentes, búsqueda,
filtros combinados, consulta AJAX y coincidencia de datos entre tabla y PDF.
Las pruebas JavaScript de Asistencias comprueban también la restauración del
checkbox al paginar, limpiar y usar Atrás/Adelante.

Ejecutar exclusivamente con SQLite en memoria, sesiones/cache/mail de prueba y
almacenamiento temporal verificados antes de inicializar Laravel. No usar MySQL
real ni `TransicionDocumentalTest.php`. No se requieren migraciones nuevas.

## Archivos de esta modificación

```text
app/Services/CursosConsulta.php (nuevo)
app/Services/ReportesAdministrativos.php
app/Http/Controllers/Admin/AdminDashboardController.php
app/Http/Controllers/Admin/AsistenciaController.php
app/Http/Controllers/Admin/BoletaController.php
app/Http/Controllers/Admin/InscripcionController.php
app/Http/Controllers/Admin/MensualidadController.php
app/Http/Controllers/Admin/NotaController.php
app/Http/Controllers/Admin/ReporteController.php
app/Http/Controllers/Admin/ReporteVisorController.php
app/Http/Controllers/Admin/SugerenciasFiltroController.php
app/Http/Controllers/Docente/AsistenciaController.php
app/Http/Controllers/Docente/NotasController.php
resources/views/components/consulta-cursos-alcance.blade.php (nuevo)
resources/views/components/consulta-cursos-opciones.blade.php (nuevo)
resources/views/components/filtros-autocompletado.blade.php
resources/views/admin/alumnos.blade.php
resources/views/admin/asistencias.blade.php
resources/views/admin/boleta-detalle.blade.php
resources/views/admin/boletas.blade.php
resources/views/admin/inscripciones.blade.php
resources/views/admin/mensualidades.blade.php
resources/views/admin/notas.blade.php
resources/views/admin/reportes/asistencias.blade.php
resources/views/admin/reportes/cursos.blade.php
resources/views/admin/reportes/partials/contenido.blade.php
resources/views/docente/asistencia.blade.php
resources/views/docente/notas.blade.php
resources/js/admin/alumnos.js
resources/js/admin/asistencias-filtros.js
resources/js/docente/notas.js
tests/Feature/CursosConsultaTest.php (nuevo)
tests/Feature/ReportesAdministrativosTest.php
tests/Feature/ReporteMensualidadesPdfTest.php
tests/Feature/ReporteVisorTest.php
tests/Feature/ElegibilidadMensualidadesTest.php
tests/js/asistencias-filtros.test.mjs
docs/filtros-cursos-historicos.md (nuevo)
```

También se recompilaron los recursos de `public/build` mediante Vite. No se
modificaron rutas, modelos, migraciones, plantillas PDF ni operaciones de escritura.
La aserción de texto de ElegibilidadMensualidadesTest se actualizó de «Sin registro»
a la etiqueta ya existente «Sin mensualidad registrada»; no se cambió Pagos.

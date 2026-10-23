# Navegación independiente de Reportes

La portada `/admin/reportes` conserva las seis tarjetas. Solicitudes mantiene su acceso independiente desde el menú administrativo. Cada tarjeta abre una vista propia mediante la ruta existente `admin.reportes.show`, sin cambiar sus URL ni el grupo `auth` / `role:admin`.

Las seis vistas reutilizan `components/admin-reportes-layout.blade.php` y `admin/reportes/partials/contenido.blade.php`. Las consultas, indicadores y paginación permanecen en `ReportesAdministrativos`; las descargas de Cursos, Maestros y Mensualidades mantienen sus controladores y plantillas.

## Asistencias

El filtro `detalle_curso_id` identifica la asignación de materia y se valida contra `curso_id`. El catálogo incluye las asignaciones del curso sin restringirlas a materias activas, para conservar la consulta histórica. Al cambiar de curso se limpia la materia seleccionada y se envía automáticamente el formulario para cargar sus opciones reales. Sin JavaScript se conserva la alternativa Aplicar filtros. Los cursos sin asignaciones muestran un mensaje específico.

Asistencias tiene una vista propia con estilos limitados a esa página. El botón PDF permanece visible y se habilita con curso y materia para cualquier estado válido. Al editar cualquier filtro se invalida el enlace anterior hasta aplicar los cambios. La explicación sobre el alcance del documento se encuentra en una ayuda desplegable.

El listado conserva registros históricos. Con Todos los estados, el PDF institucional utiliza alumnado con inscripción aprobada actual y el control completo de la materia dentro del período seleccionado. Con Presente, Ausente o Permiso se descarga un histórico filtrado, incluidos alumnos que ya no están inscritos. La página explica esta diferencia y conserva curso, materia, estado y fechas. El histórico muestra cada registro real por fecha, alumno, materia, estado y registrador; no es una matriz completa del curso.

Alumnos inscritos dispone de una descarga propia en `admin.reportes.alumnos.pdf`, con curso, estado del alumno, mes y año, compartiendo población con su tabla. Calificaciones conserva su listado sin nueva descarga PDF. Las boletas y las solicitudes siguen siendo funcionalidades independientes. La portada ya no muestra un enlace separado de inscripciones; Solicitudes conserva su acceso en el menú administrativo y su PDF original.

## Validación aislada

```powershell
php -d xdebug.mode=off -d memory_limit=768M vendor/phpunit/phpunit/phpunit --bootstrap tests/sqlite-memory-bootstrap.php --filter 'ReportesAdministrativosTest|ReporteCursosPdfTest|ReporteInscripcionesPdfTest|ReporteMaestrosPdfTest|ReporteMensualidadesPdfTest|ControlAsistenciaPdfTest|AdminAsistenciasReporteTest' --do-not-cache-result
```

El bootstrap comprueba SQLite en memoria y evita utilizar configuración cacheada o la conexión real. No ejecutar `artisan migrate` contra el entorno de la aplicación. Los fixtures de las pruebas se crean únicamente en la base efímera en memoria.

La interacción de curso dependiente y la invalidación del enlace se verifican adicionalmente en memoria, sin navegador ni servidor activo:

```powershell
node tests/Frontend/asistencias-reportes.test.cjs
```

Se conserva el CSS adaptable existente, con filtros apilados y tablas desplazables en pantallas pequeñas. La revisión automática de Blade no sustituye una comprobación visual en navegadores y dispositivos reales.

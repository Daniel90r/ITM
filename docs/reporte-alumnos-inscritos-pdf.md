# Reporte de Alumnos inscritos

La portada `/admin/reportes` conserva cinco tarjetas. Se retira únicamente el enlace separado de inscripciones; el menú administrativo mantiene Solicitudes, sus operaciones y la descarga `admin.reportes.inscripciones.pdf`, sin cambios de ruta, plantilla o significado.

La nueva descarga `/admin/reportes/alumnos/pdf` (`admin.reportes.alumnos.pdf`) está dentro del grupo `auth` y `role:admin`. Usa Dompdf, logotipos oficiales locales, carta horizontal, encabezado y pie institucionales, resumen de alumnos únicos y una tabla con las mismas columnas que la pantalla. La respuesta es privada y sin caché; no se almacena públicamente.

## Alcance y filtros

- Solo alumnos con inscripciones **actualmente aprobadas**. Pendientes, rechazadas e inactivas no constituyen inscripciones aprobadas.
- Una fila por alumno; cursos correspondientes agrupados sin repeticiones. El filtro activo/inactivo corresponde al **alumno**, no al estado de su solicitud. No se añade un filtro nuevo sobre `inscripciones.activa`.
- Se conservan curso y estado del alumno y se añaden mes y año. En la pantalla y su descarga, si no se envía año ni un mes específico se aplica el año actual del servidor Laravel (`now()->year`). Solo año consulta todo ese año. Mes exige año; valores permitidos: meses 1–12 y años 1900–2100.
- El período se aplica a `fecha_inscripcion`, tanto al seleccionar alumnos como al cargar sus cursos e inscripciones. El intervalo incluye el inicio y excluye el primer día del mes/año siguiente.
- La columna «Fecha registrada de solicitud/inscripción» no representa fecha de aprobación. El sistema no conserva una fecha general de aprobación verificable; este informe no reconstruye la matrícula que existía en aquel mes.
- La tabla y el PDF comparten `consultaAlumnos`, `inscripcionesAlumnos` y `filaAlumno`. La pantalla pagina 25 alumnos; el PDF exporta todos los resultados, sin el parámetro `page`.
- El resultado vacío mantiene encabezado, período, filtros y resumen cero, con un mensaje explícito.

## Archivos

Modificados: `routes/admin.php`, `ReporteController.php`, `ReportesAdministrativos.php`, `admin/reportes/index.blade.php`, `admin/reportes/partials/contenido.blade.php` y `ReportesAdministrativosTest.php`.

Creados: `admin/reportes/pdf/alumnos.blade.php`, `ReporteAlumnosPdfTest.php` y esta documentación. Se actualiza también la documentación de navegación para reflejar la nueva descarga.

## Validación aislada

```powershell
php -d xdebug.mode=off -d memory_limit=768M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'ReporteAlumnosPdfTest|ReportesAdministrativosTest|ReporteInscripcionesPdfTest|ReporteCursosPdfTest|ReporteMensualidadesPdfTest|ReporteMaestrosPdfTest|ControlAsistenciaPdfTest' --do-not-cache-result
```

El bootstrap fuerza SQLite en memoria y almacenamiento temporal. Las pruebas cubren mes/año, mes sin año, límites de período, curso, estado del alumno, alumnos con varias inscripciones, exclusión de estados no aprobados, coincidencia entre pantalla y datos del PDF, exportación de 26 alumnos, vacío, roles, métodos y conservación de Solicitudes.

La variable opcional `ITM_ALUMNOS_PDF_VISUAL=1` exporta únicamente una muestra de fixtures sintéticos a `storage/app/reportes-referencia/alumnos-inscritos-prueba.pdf`, fuera del disco público. No utiliza datos reales ni modifica documentos históricos.

Resultado final: **79 pruebas aprobadas, 6.697 aserciones**, sin fallos ni omisiones, en una única ejecución de las siete clases indicadas. La muestra de 26 alumnos ocupa dos páginas; se rasterizó con Windows.Data.Pdf y se revisaron encabezado, logotipos, columnas, nombres, fechas, totales y numeración sin cortes observados. Sintaxis PHP y `git diff --check` satisfactorios. No se ejecutaron comandos de migración ni operaciones sobre la base real.


## Año actual predeterminado

La normalización se realiza en la validación compartida de `ReporteController`, para que tabla y PDF reciban exactamente los mismos filtros. No se fija un año en código: se utiliza el reloj y la zona horaria configurada en Laravel. El cambio de diciembre a enero se verifica con tiempo simulado.

- La entrada sin filtros utiliza el año actual, también al pulsar Limpiar.
- Un año anterior enviado explícitamente se conserva, sin sustituirlo por el actual.
- El campo es editable y obligatorio. Un valor vacío, texto, arreglo o fuera del rango existente 1900–2100 se rechaza; no se convierte en una consulta sin año.
- Se conserva la regla anterior de mes específico sin año: se rechaza. Los enlaces antiguos con mes y año válidos siguen funcionando. Los enlaces sin año ahora tienen el alcance predeterminado del año actual, en vez de consultar todos los años.
- Curso, estado del alumno, mes y año se conservan en paginación y descarga. El PDF exporta todos los resultados filtrados, no solo la página visible.
- El mensaje vacío de pantalla identifica el año consultado y los filtros seleccionados.

Los ajustes de la vista parcial están condicionados a Alumnos inscritos. Mensualidades conserva su año opcional con el indicador Todos. No se modifican servicios de consulta, plantillas PDF, rutas ni permisos.

Archivos de este ajuste:

```text
app/Http/Controllers/Admin/ReporteController.php
resources/views/admin/reportes/partials/contenido.blade.php
tests/Feature/AlumnosReporteAnioTest.php
tests/Feature/ReportesAdministrativosTest.php
docs/reporte-alumnos-inscritos-pdf.md
```

Las pruebas utilizan `tests/documentos-isolated-bootstrap.php`, SQLite en memoria y almacenamiento temporal. Se verifica el año predeterminado antes y después de Año Nuevo, la consulta de años anteriores, paginación, filtros del PDF, Limpiar, valores inválidos, vacíos y acceso exclusivo del administrador. No se modifican registros reales ni se ejecutan migraciones sobre la base real. No se hace commit ni push.

Resultado final de este ajuste: **45 pruebas aprobadas, 641 aserciones**, sin fallos, en una sola ejecución de `AlumnosReporteAnioTest`, `ReporteAlumnosPdfTest` y `ReportesAdministrativosTest`. Se comprueba además que Mensualidades mantiene su año opcional y que las rutas originales de Solicitudes conservan su funcionamiento.

# Asistencias históricas mensuales y anuales

## Modalidades y compatibilidad

En Reportes → Asistencias, Mensual requiere mes y año; Anual requiere año y no admite mes. Ambas requieren curso y materia para descargar. Se conserva el filtro de estado y la misma ruta protegida `admin.asistencias.pdf`, con `auth` y `role:admin`. La tabla histórica aplica el mismo período a sus consultas y mantiene su paginación.

La pantalla ofrece solo Mensual y Anual. Al entrar sin modalidad se aplica Mensual con el mes y año actuales de El Salvador; esos valores forman parte de la consulta, la paginación y el enlace PDF. Limpiar vuelve a esos valores predeterminados. Desde/Hasta no se muestran y se rechazan si llegan a esta pantalla, incluso sin modalidad o como parámetros vacíos.

El módulo administrativo `/admin/asistencias` sigue utilizando Desde/Hasta y `admin.asistencias.pdf`. Por eso se conservan los controladores, servicios y plantillas anteriores. Las descargas directas antiguas sin modalidad mantienen el control institucional de nómina aprobada actual para Todos los estados y el PDF histórico para estados específicos. No se modifican el registro docente ni sus rutas.

Con modalidad explícita se rechazan parámetros Desde/Hasta, incluso si coinciden con el mes/año, en lugar de ignorarlos. También se rechazan mes en Anual y mes/año sin modalidad. La interfaz habilita únicamente los campos aplicables y exige volver a aplicar filtros antes de descargar.

## Criterios de datos

`PeriodoAsistencia` valida el período y obtiene sus límites inclusivos. `FiltrosHistoricosAsistencia` aplica esos límites tanto al listado como a la consulta histórica. `AsistenciasPeriodo` construye ambas modalidades desde el mismo conjunto de datos, sin exigir inscripción vigente ni asignación opcional actual a alumnos históricos.

- Una fecha procede exclusivamente de `asistencias.fecha`, limitada al curso, detalle de curso, período y estado indicados. No se generan sábados ni clases desde el horario semanal.
- Los alumnos son aquellos que tienen registros incluidos en el período. Se mantienen los retirados y alumnos sin inscripción actual. Un alumno sin ningún registro coincidente no se inventa como fila.
- P, A y PM se cuentan por separado. Sin registro es una ausencia de información, nunca una ausencia académica.
- Se agrupan primero los registros por alumno–materia–fecha antes de aplicar el estado. Si hay más de uno, se señala `?` y se excluye toda esa celda de los totales, incluso si sus estados difieren. No se elige el último ni el primero.
- Las fechas distintas incluyen las fechas reales de celdas señaladas, pero sus marcas no suman P/A/PM. Los filtros de estado delimitan también las fechas del documento.
- Se conservan nombres completos, observaciones y registradores reales. El horario mostrado es la configuración actual del curso, no una reconstrucción histórica.
- La tabla de pantalla conserva las filas originales; ante duplicados puede incluir más registros que las marcas válidas totalizadas en el PDF. El documento identifica esas celdas y su exclusión, sin alterar los registros.

## Presentación

El mensual genera una matriz con diez fechas por bloque como máximo, totales P/A/PM de todo el período por alumno y cantidad de fechas separada. Se distribuyen los alumnos en grupos de hasta diez, reducidos a seis o tres para nombres largos. Los totales de referencia se repiten y se advierte que no deben sumarse entre páginas o bloques.

El anual presenta los doce meses con cantidades P/A/PM, tres totales anuales por alumno y el resumen de fechas distintas por mes y año. Los meses sin registros se identifican como «Sin registro». Para mantener legibilidad se utilizan grupos de hasta seis alumnos, reducidos para nombres muy largos. El detalle de fechas se consulta seleccionando Mensual para el mismo curso, materia, año y estado; no se incorpora un anexo anual de fechas.

Ambos reutilizan sin modificar los parciales institucionales de logotipos, encabezado, datos académicos y estilos. Conservan firmas de Director e Instructor, observaciones en anexo cuando existen y pie con numeración. Los nuevos documentos se identifican como históricos mensuales/anuales, sin presentarse como el control completo de nómina vigente.

## Archivos de esta entrega

```text
app/Services/PeriodoAsistencia.php
app/Services/AsistenciasPeriodo.php
app/Services/FiltrosHistoricosAsistencia.php
app/Http/Controllers/Admin/ReporteController.php
app/Http/Controllers/Admin/AsistenciaController.php
resources/views/admin/reportes/asistencias.blade.php
resources/views/admin/reportes/pdf/asistencia-periodo.blade.php
tests/Feature/AsistenciasPeriodoTest.php
tests/Feature/ReporteAlumnosPdfTest.php
tests/Frontend/asistencias-reportes.test.cjs
docs/asistencia-mensual-anual.md
```

En `ReporteAlumnosPdfTest` solo se actualiza la expectativa pendiente de la portada a cinco tarjetas, conforme al cambio de navegación previamente aprobado. No se altera ningún otro reporte ni su PDF.

## Verificación aislada y muestras

Se usa `tests/documentos-isolated-bootstrap.php`, que exige SQLite en memoria y redirige el almacenamiento a una ubicación temporal. Las pruebas incluyen varias fechas, varios meses/años, conciliación anual con doce mensuales, estados, alumnos retirados, cursos y materias distintos, períodos vacíos, permisos y parámetros contradictorios. La prueba de duplicados retira el índice únicamente del esquema SQLite efímero; nunca de la base real.

Las muestras contienen exclusivamente fixtures sintéticos identificados como prueba: trece alumnos, trece fechas en octubre y una en noviembre. El mensual tiene cuatro páginas y el anual tres. Se comprueba la cantidad de páginas y que las cajas de texto permanezcan dentro de los límites de impresión. Los PDF se rasterizan con Windows.Data.Pdf y se revisan visualmente.

```powershell
$env:ITM_ASISTENCIA_PERIODOS_VISUAL = '1'
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter AsistenciasPeriodoTest
node tests/Frontend/asistencias-reportes.test.cjs
```

Las muestras se escriben opcionalmente fuera del disco público:

- `storage/app/reportes-referencia/asistencia-mensual-prueba.pdf`
- `storage/app/reportes-referencia/asistencia-anual-prueba.pdf`

Resultados de la implementación inicial de modalidades (anteriores a la simplificación de interfaz), sin sumar ejecuciones distintas:

- Regresión seleccionada: **117 pruebas aprobadas, 7062 aserciones**, sin fallos. Incluye las nuevas modalidades, control institucional anterior, reportes históricos por estado, registro docente y los otros PDF.
- Comprobación específica final de las nuevas modalidades, incluyendo límites de texto: **4 pruebas aprobadas, 7211 aserciones**.
- JavaScript de filtros, descarga y cambio de modalidad: **4 pruebas aprobadas**, sin fallos ni omisiones.
- Rasterización y revisión de las cuatro páginas mensuales y las tres anuales: encabezados, logotipos, nombres, columnas, totales, firmas y pies numerados legibles. Las pruebas de geometría no detectaron texto fuera de los límites de la página.

No se modifican datos reales, documentos históricos, migraciones, permisos ni los PDF existentes. No se hace commit ni push.

## Limitaciones

- Las modalidades nuevas no reconstruyen una nómina histórica: muestran alumnos con registros reales. Sin registros no se puede afirmar si se impartió clase o si un alumno debía asistir.
- Los duplicados requieren revisión administrativa posterior; no se corrigen automáticamente.
- El detalle anual se consulta con el reporte mensual; no hay anexo anual de fechas ni enlaces internos al detalle en esta versión.
- La selección dinámica de campos usa JavaScript, al igual que la navegación móvil existente.


## Simplificación de la pantalla de Reportes

Se retira únicamente el acceso visible al intervalo en Reportes, no su descarga compartida. Se actualizan `ReporteController`, la vista de Asistencias, `ReportesAdministrativosTest`, `AsistenciasPeriodoTest`, las pruebas JavaScript de filtros y el selector de capturas de `staff-responsive.cjs`. No se modifican los servicios de cálculo, las rutas ni las plantillas PDF.

La comprobación de pantalla verifica dos opciones, Mensual seleccionada por defecto, ausencia de campos de fecha, filtros aplicados al enlace de descarga, rechazo de fechas antiguas y campos obligatorios. La compatibilidad del PDF por intervalo se sigue comprobando separadamente con sus pruebas existentes. La validación visual usa HTML generado con SQLite y datos sintéticos, con HTTP/HTTPS bloqueado en el navegador de prueba.

Resultado final de esta simplificación: **64 pruebas PHP aprobadas, 13665 aserciones**, sin fallos, en una sola ejecución de `AsistenciasPeriodoTest`, `ReportesAdministrativosTest`, `ControlAsistenciaPdfTest` y `AdminAsistenciasReporteTest`. Por separado, **4 pruebas JavaScript aprobadas**. Mensual y Anual superaron cada una cinco comprobaciones de navegador (320, 375, 390, 768 y 1440px), sin desbordamientos ni fallos de navegación. Se revisaron visualmente las capturas móviles y de escritorio; el selector solo contiene dos opciones y Anual no muestra Mes. No se modificó ningún PDF ni la base de datos real.

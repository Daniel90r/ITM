# Reporte anual de Cursos

## Pantalla y validación

Reportes → Cursos muestra solo Curso, Estado actual del curso y Año obligatorio. Al entrar o pulsar Limpiar se utiliza el año actual de El Salvador. No existen selectores de Modalidad ni Mes, ni campos ocultos que los envíen. Se conservan Aplicar filtros, Limpiar y Descargar PDF; editar filtros invalida el enlace hasta volver a aplicarlos.

La misma validación de `ReporteController::filtros()` se utiliza en pantalla y descarga. Se normaliza internamente a `modalidad=anual`, con año 1900–2100 y validación de curso y estado. El año omitido utiliza el actual; un año explícitamente vacío o inválido se rechaza.

Los parámetros `mes` (incluso vacío) y cualquier modalidad distinta de `anual`, incluida `modalidad=mensual`, se rechazan con un mensaje claro, sin convertir silenciosamente una consulta mensual en anual. Las URL anuales anteriores con `modalidad=anual` siguen funcionando.

## Dependencias conservadas

La ruta `admin.reportes.cursos.pdf` se utiliza desde Reportes y llama a `ReportesAdministrativos::cursosPdf()`. La tabla y el PDF comparten `consultaCursos()`. No se encontró otro controlador consumidor del informe mensual. Se conservan la ruta, los servicios y las ramas internas anteriores; no se eliminan componentes compartidos. Las entradas HTTP de Cursos ofrecen exclusivamente el informe anual, incluso cuando no se proporcionan filtros.

No se modifican Asistencias, Mensualidades, Alumnos, Maestros ni sus PDF. Los filtros de año de Cursos se normalizan únicamente para ese tipo de reporte.

## Alcance y cálculos

Se seleccionan cursos con al menos una inscripción **actualmente aprobada** cuya `fecha_inscripcion` esté entre el 1 de enero y el 31 de diciembre del año seleccionado, ambos incluidos. No se filtra por año lectivo ni por las fechas de inicio o finalización del curso.

La fecha utilizada es la registrada de solicitud/inscripción, no la fecha de aprobación. Puede haber sido actualizada al reenviar una solicitud rechazada. El reporte no reconstruye históricamente estados, visibilidad ni cupos.

- `whereHas` selecciona cursos sin multiplicarlos por sus relaciones.
- `periodo_count` se conserva como nombre interno del conteo anual, mostrado como aprobadas con fecha en el año.
- `aprobadas_count` conserva todas las inscripciones actualmente aprobadas del curso, incluidas las de otros años.
- Cupo máximo actual: `Curso::CUPOS_MAXIMO`, actualmente 20.
- Disponibles actuales: `max(0, CUPOS_MAXIMO - aprobadas_count)`, sin restar únicamente las inscripciones del año.
- Estado actual: accesor canónico `estado_curso`; visibilidad actual: `activo`.
- Pendientes, rechazadas e inactivas no cuentan como aprobadas. Se conserva la regla existente sin agregar filtros diferentes sobre `activa` o estado del alumno.

Los indicadores incluyen todos los resultados filtrados. La pantalla pagina 25 cursos; el PDF exporta todos, sin arrastrar `page`. Los totales cuentan inscripciones, no alumnos únicos entre cursos distintos.

## PDF y muestras

El encabezado identifica **REPORTE ANUAL DE CURSOS** y **AÑO CONSULTADO**, sin mostrar un mes. Mantiene logotipos, orientación horizontal para las siete columnas, encabezados de tabla repetidos y numeración de páginas. Los textos y el mensaje vacío se refieren al año consultado.

La muestra sintética anual incluye 28 cursos con dos inscripciones aprobadas cada uno, fechadas en septiembre y octubre de 2026. Total anual: 56; aprobadas actuales: 56; disponibles actuales: 504. Se guarda fuera del disco público en `storage/app/reportes-referencia/cursos-anual-prueba.pdf`.

```powershell
$env:ITM_CURSOS_PERIODOS_VISUAL = '1'
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter CursosPeriodoTest
node tests/Frontend/cursos-reportes.test.cjs
```

El bootstrap exige SQLite en memoria y almacenamiento temporal. La muestra contiene exclusivamente datos ficticios. El navegador utiliza HTML renderizado con ese mismo entorno, perfil propio y HTTP/HTTPS bloqueado.

Resultado final: **88 pruebas PHP aprobadas, 16196 aserciones**, sin fallos, en una ejecución de regresión de Cursos, reportes administrativos y los PDF existentes. Por separado, **2 pruebas JavaScript aprobadas** para la invalidación de descargas. Las cinco comprobaciones de navegador (320, 375, 390, 768 y 1440px) pasaron; se revisaron visualmente escritorio, móvil y la muestra anual de tres páginas. Las pruebas verifican tres campos visibles, año predeterminado y obligatorio, rechazo de parámetros mensuales, filtros, vacíos, permisos, coincidencia tabla/PDF y exportación completa.

## Archivos de esta simplificación

```text
app/Http/Controllers/Admin/ReporteController.php
app/Services/ReportesAdministrativos.php
resources/views/admin/reportes/cursos.blade.php
resources/views/admin/reportes/pdf/cursos.blade.php
tests/Feature/CursosPeriodoTest.php
tests/Feature/ReportesAdministrativosTest.php
tests/Frontend/cursos-reportes.test.cjs
docs/reporte-cursos-mensual-anual.md
```

El nombre del documento se conserva para mantener sus enlaces existentes.

## Limitaciones

El informe depende del estado y fecha registrados actualmente: una modificación posterior puede cambiar los resultados de un año anterior. No representa la fecha de aprobación, el estado del curso entonces ni su disponibilidad histórica. Los cursos sin inscripciones aprobadas coincidentes quedan fuera.

No se modifican datos reales, estados, permisos ni cálculos de cupos. No se ejecutan migraciones sobre la base real, ni commit ni push.

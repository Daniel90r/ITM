# Reporte de Maestros y Asignaciones ITM

Entrega: 21 de octubre de 2026. Alcance exclusivo: Maestros y Asignaciones; no se implementaron Pagos ni Asistencia.

## Referencia y formato

Se examinó `referencias-reportes/Reporte de Maestros y Asignaciones ITM.docx` mediante su contenido OOXML: texto, tablas, estilos, medidas, imágenes, relaciones y pie de página. Se conserva carta vertical, márgenes superior 43,2 pt, laterales 46,8 pt e inferior 39,6 pt; título de 16 pt y subtítulo de 10 pt; logotipos a ambos lados; metadatos de dos columnas; tabla de cinco columnas; resumen de tres celdas; bordes de 0,5 pt y sombreado alterno. La tabla se ajusta al área imprimible: su ancho declarado en Word supera el área útil. Se incluye la materia dentro de «Curso asignado» para distinguir asignaciones.

Los dos logotipos del Word son idénticos byte por byte a `resources/images/reportes/itm.jpg` y `resources/images/reportes/escudo-inscripciones.jpg`; se reutilizaron sin editarlos. Se reproduce el texto institucional del pie: «ITM Aguilares • Reporte de Maestros y Asignaciones • Gestión Académica», con numeración en cada página. Se retiró la anotación interna «analizar» de la plantilla.

Limitación de comparación: no se dispone de un PDF renderizado del Word original; la automatización de Word había fallado en este entorno. La reproducción se basa en propiedades verificadas del DOCX. Sí se rasterizó y revisó visualmente el PDF final. Se utiliza DejaVu Sans en lugar de las fuentes Office del documento. La cabecera permite varias líneas para evitar superposición con los logotipos.

## Modelo, historial y cálculos

Se revisaron Maestro, Curso, Materia, DetalleCurso, AsignacionDocente, la migración de asignaciones, MaestroController y las pruebas de historial docente. La relación efectiva es maestro + curso + materia mediante `detalle_curso_id`. Los estados reales son `activa`, `programada` y `finalizada`; la asignación activa es la fuente del acceso académico. Los cambios de maestro finalizan la asignación anterior y conservan el historial.

El informe mantiene el alcance del reporte administrativo existente: **asignaciones activas registradas**. No mezcla programadas ni finalizadas con activas. Sin filtro de curso también incluye docentes sin asignación activa, identificados expresamente. Con filtro de curso selecciona docentes que tengan asignación activa en ese curso. La pantalla conserva sus columnas, indicadores y paginación anteriores.

- Pantalla y PDF comparten consulta, precarga de relaciones y validación.
- Filtros: `curso_id` y `estado` del docente (`activo`/`inactivo`), no estado de la asignación.
- Docentes registrados: docentes únicos del conjunto filtrado, no cantidad de renglones.
- Asignaciones activas: registros activos de esos docentes, restringidos al curso seleccionado si corresponde. Los totales incluyen todos los resultados, no solo una página.
- Pendientes: **No definido**. No existe ese estado ni una regla verificable que lo equipare a programada o a docente sin asignación; no se inventa un cero ni una cantidad.
- Año: años lectivos reales de los cursos con asignaciones incluidas; si se selecciona un curso sin resultados, se utiliza su año registrado. Sin información aparece «No disponible».
- Ciclo: **No registrado**. No hay un campo o calendario que lo defina.
- Período: asignaciones activas registradas sin restricción temporal; el reporte existente no tiene filtros temporales.
- Fecha de asignación: `fecha_inicio`, explicada como inicio registrado. Si es nula se muestra un guion; no se sustituye por `created_at`, `updated_at` o la fecha de generación. La migración histórica pudo crear asignaciones sin fecha de inicio.
- Observaciones: motivo almacenado y advertencias de disponibilidad o inconsistencia. Se señalan docente/curso/materia inactivos, diferencias con el maestro de `detalle_cursos` y asignaciones activas ambiguas, igual que la pantalla. No se corrigen datos desde el informe.
- Textos extensos continúan en filas pequeñas, sin aumentar los indicadores. Blade escapa los textos. No se exportan DUI, teléfonos o credenciales.

## Integración y archivos

Botón **Descargar PDF** en `/admin/reportes/maestros`. Conserva los filtros validados y exporta todo el conjunto, sin aplicar el número de página. Descarga `GET /admin/reportes/maestros/pdf`, nombre `admin.reportes.maestros.pdf`, protegida por `web`, `auth` y `role:admin`. Nombre de archivo `reporte-maestros-asignaciones-YYYY-MM-DD-HHMMSS.pdf`, con fecha y hora de El Salvador. Usa Dompdf, respuesta privada sin caché y recursos locales embebidos; no publica documentos en `public`.

Archivos modificados en esta entrega:

- `app/Services/ReportesAdministrativos.php`: consulta común de Maestros y datos PDF.
- `app/Http/Controllers/Admin/ReporteController.php`: método de descarga y pie institucional.
- `routes/admin.php`: ruta GET protegida.
- `resources/views/admin/reportes/index.blade.php`: botón para Maestros.

Archivos creados:

- `resources/views/admin/reportes/pdf/maestros.blade.php`
- `tests/Feature/ReporteMaestrosPdfTest.php`
- `docs/reporte-maestros-pdf.md`

## Pruebas y datos reales

```powershell
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'ReporteMaestrosPdfTest|ReportesAdministrativosTest|TeacherAssignmentHistoryTest|ReporteCursosPdfTest|ReporteInscripcionesPdfTest' --do-not-cache-result
```

Resultado: **56 pruebas aprobadas, 591 aserciones**, 53,020 segundos. Incluye seis pruebas nuevas y regresión del módulo, historial docente y los dos PDF anteriores. Cobertura: descarga PDF real, nombre, privacidad, invitado, roles, parámetros inválidos, método de escritura rechazado, vacío, filtros compartidos, docentes únicos, varias materias del mismo docente/curso, exclusión de programadas/finalizadas, docente sin asignación, fechas ausentes, observaciones, inconsistencias, textos extensos, escape HTML, ausencia de DUI, consultas de solo lectura y 41 docentes renderizados en varias páginas. El bootstrap fuerza SQLite en memoria y almacenamiento temporal aislado; la nueva prueba verifica el PDO antes de preparar su esquema. No se ejecutaron migraciones contra MySQL.

También se comprobó sintaxis PHP de controlador, servicio y prueba, `git diff --check` y los middleware de la ruta.

La muestra real se generó mediante el mismo método del controlador, dentro de una transacción MySQL **READ ONLY**, finalizada con rollback. Se contrastaron los totales contra conteos independientes: **1 docente, 3 asignaciones activas, año lectivo 2026**. Las tres fechas de inicio estaban sin registrar y se mostraron con guion. El PDF tiene una página y 1.066.308 bytes. Windows.Data.Pdf produjo una imagen que se revisó visualmente: logotipos, tabla con las tres materias, resumen, observaciones, notas y pie caben dentro de la página. No se efectuó una sesión gráfica autenticada; descarga y permisos se verificaron mediante HTTP de prueba.

Artefactos privados locales:

- `storage/app/reportes-referencia/maestros-datos-reales.pdf`
- `storage/app/reportes-referencia/maestros-datos-reales-1.png`

El verificador temporal fue retirado. No se modificaron datos ni estructura de la base real, no se ejecutaron comandos destructivos y no se hicieron commits ni push. Se conservaron los cambios previos de Cursos e Inscripciones.

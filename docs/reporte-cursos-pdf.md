# Reporte de Cursos PDF

Implementado el 21 de octubre de 2026. Esta entrega comprende únicamente Cursos, conforme a la solicitud de revisar el primer informe antes de continuar.

## Referencias examinadas

Los cinco DOCX en `referencias-reportes` son legibles como paquetes OOXML. Se examinaron sus textos, tablas, secciones, relaciones e imágenes. Cursos contiene: carta vertical, márgenes superior 43,2 pt, laterales 46,8 pt e inferior 39,6 pt; título centrado de 22 pt; logo ITM izquierdo; escudo derecho; tabla central de período/fecha; tabla de cinco columnas; resumen de tres celdas. Se conservaron esas secciones, bordes finos, sombreado alterno y recursos originales. El escudo se extrajo y rotó 180 grados según la transformación declarada en el DOCX. Los ejemplos numéricos y las filas vacías de ejemplo no se exportan.

Limitación: la automatización de Word falló con `CO_E_SERVER_EXEC_FAILURE`; no se obtuvo un PDF renderizado del original para comparar ambas páginas visualmente. La implementación se basa en el formato OOXML y las imágenes inspeccionadas, no en una comparación píxel a píxel. El PDF generado sí se rasterizó con Windows.Data.Pdf y se revisó visualmente. La fuente es DejaVu Sans para conservar caracteres españoles; no se incorporó la fuente Play del título del Word. Una referencia PDF permitiría cerrar la comparación visual final.

## Integración y datos

- Pantalla: `/admin/reportes/cursos`.
- Descarga: `GET /admin/reportes/cursos/pdf`, nombre `admin.reportes.cursos.pdf`.
- Middleware comprobado mediante `route:list`: `web`, `auth`, `role:admin`.
- Botón **Descargar PDF** conserva `curso_id` y `estado` validados; la paginación no limita la exportación.
- Pantalla y PDF comparten consulta y validación. La vista existente conserva sus columnas y funciones.
- Se cuentan únicamente inscripciones aprobadas, sin joins que multipliquen resultados. Se reutilizan `estado_curso` y `Curso::CUPOS_MAXIMO`; disponibilidad `max(0, 20 - aprobadas)` por curso. El esquema existente impide repetir alumno/curso.
- Se incluyen fechas reales de inicio y fin, con guion cuando no están registradas.
- El período indica todos los años y meses: Cursos actualmente solo filtra por curso y estado. No se definió un criterio temporal nuevo ni se limita silenciosamente al mes de emisión.
- Fecha de generación en `America/El_Salvador`, nombre descriptivo, numeración de páginas, cabecera de tabla repetible y resumen global. Exportación privada sin caché, sin descargar imágenes remotas ni guardar documentos sensibles en `public`.

## Archivos

Modificados:

- `app/Http/Controllers/Admin/ReporteController.php`
- `app/Services/ReportesAdministrativos.php`
- `routes/admin.php`
- `resources/views/admin/reportes/index.blade.php`

Creados:

- `resources/views/admin/reportes/pdf/cursos.blade.php`
- `resources/images/reportes/itm.jpg`
- `resources/images/reportes/escudo-cursos.png`
- `tests/Feature/ReporteCursosPdfTest.php`
- `docs/reporte-cursos-pdf.md`

## Verificación

Comando:

```powershell
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'ReporteCursosPdfTest|ReportesAdministrativosTest' --do-not-cache-result
```

Resultado final: **30 pruebas aprobadas, 366 aserciones**, 38,349 segundos. Incluye cinco pruebas nuevas de descarga PDF real, vacío, nombre, caché privada, acceso de invitado y otros roles, parámetros inválidos, método de escritura rechazado, filtros, correspondencia de indicadores con pantalla, exclusión de pendientes/rechazadas, sobrecupo y 65 cursos exportados en varias páginas. También ejecuta las 25 pruebas de regresión del módulo. El bootstrap fuerza SQLite en memoria y almacenamiento temporal; las pruebas comprueban el controlador efectivo antes de preparar su esquema. No ejecutan migraciones contra MySQL. Un fallo inicial de la prueba de nombre por cambio de segundo se corrigió fijando el reloj del test.

Sintaxis PHP de controlador y servicio correcta. `git diff --check` sin errores de whitespace. No se realizaron commits ni push.

Comprobación real: una transacción MySQL `READ ONLY`, finalizada con rollback, produjo **3 cursos, 6 inscripciones aprobadas y 54 cupos disponibles**, en una página. No se modificaron registros ni estructura. El verificador temporal fue retirado. Artefactos privados locales para revisión:

- `storage/app/reportes-referencia/cursos-datos-reales.pdf`
- `storage/app/reportes-referencia/cursos-datos-reales.png`

Los totales reflejan los datos en el momento de la comprobación. No se implementaron aún Inscripciones, Maestros, Pagos ni Asistencia.

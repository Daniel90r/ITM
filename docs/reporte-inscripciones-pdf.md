# Reporte de Inscripciones ITM — PDF

Entrega del 21 de octubre de 2026. Alcance limitado a Inscripciones; los otros informes no se implementaron en esta entrega.

## Formato e integración

Referencia examinada: `referencias-reportes/Reporte de Inscripciones ITM.docx`. Se leyeron el contenido OOXML, tablas, tamaños, márgenes, imágenes y sus posiciones. El formato conserva carta vertical, márgenes superior/inferior de 39,6 pt y laterales de 43,2 pt, título de 19 pt, subtítulo de 10 pt, los dos logotipos, metadatos de cuatro columnas, resumen de cuatro columnas y detalle de ocho columnas iguales con bordes de 0,5 pt. El logo ITM se reutiliza; el escudo se extrajo de este Word, sin modificar su orientación. Se utiliza DejaVu Sans para los caracteres españoles. No se dispone de una exportación del Word a PDF para una comparación visual directa del original; esta reproducción se basa en las propiedades verificadas del DOCX.

Descarga desde **Solicitudes → Descargar PDF**, o desde el enlace de Inscripciones añadido en Reportes. Ruta `GET /admin/reportes/inscripciones/pdf`, nombre `admin.reportes.inscripciones.pdf`, protegida con `web`, `auth`, `role:admin`. El nombre descargado incluye fecha y hora de El Salvador. Respuesta `application/pdf`, `Cache-Control: private, no-store`; no se almacena en una ruta pública. Dompdf utiliza imágenes locales embebidas y acceso remoto desactivado.

Se comparte validación, búsqueda, estados y orden entre pantalla y PDF mediante `ConsultaSolicitudesInscripcion`. Se conservan los filtros actuales: `estado` y `q` (nombre, apellido o código; términos combinados; porcentajes y guiones bajos literales). La exportación abarca todos los resultados, independientemente de la página visible. La pantalla mantiene sus contadores globales y su paginación de diez filas.

## Significado de los datos

- Período: **Todo el historial**, porque el listado existente no cuenta con filtros temporales. No se asigna un mes de matrícula ni un intervalo inventado.
- Responsable del encabezado: nombre real del administrador autenticado que genera el informe.
- Destinatario: **Dirección**, de acuerdo con el encabezado institucional «RESUMEN PARA DIRECCIÓN»; no se inventa un destinatario personal.
- Total: cantidad de registros filtrados de `inscripciones`, contando cada ID una sola vez. Incluye solicitudes y matrículas; únicamente `estado = aprobada` corresponde a inscripción efectiva. El estado `activa` aislado no sustituye este criterio.
- Resumen: pendientes, aprobadas y rechazadas del conjunto exportado. Cuando hay inactivas u otros estados, una nota muestra sus cantidades para explicar el total.
- Detalle: número consecutivo, nombre del alumno, identificación del curso, fecha guardada en `fecha_inscripcion`, estado, responsable auditado, motivo/observación y acción registrada.
- No existe auditoría de autor/fecha de aprobación, rechazo o reapertura. No se atribuyen esas acciones al usuario que descarga ni se deducen del estado actual. El detalle muestra **No registrado / No registrada** cuando no hay evidencia.
- `reactivaciones_inscripcion` sí contiene administrador, fecha y motivo. Se presenta expresamente como reactivación histórica, sin confundirla con una aprobación actual. Si la tabla no está disponible, se conserva la compatibilidad del listado.
- El portal también usa `observacion` para almacenar JSON del encargado. Ese contenido estructurado se excluye: no se exportan DUI, teléfonos ni datos del expediente como si fueran observaciones administrativas. Se conservan las observaciones de texto, motivos de rechazo y motivos de reactivación.
- Los textos largos continúan en filas identificadas, manteniendo el total por inscripción. Blade escapa todos los textos. Se repiten encabezados de tabla y se numeran páginas; el vacío tiene un mensaje explícito.

## Archivos de esta entrega

Modificados:

- `app/Http/Controllers/Admin/InscripcionController.php`: reutiliza la consulta compartida sin alterar operaciones de aprobación, rechazo, matrícula ni reactivación.
- `app/Http/Controllers/Admin/ReporteController.php`: descarga PDF de Inscripciones.
- `routes/admin.php`: ruta administrativa GET protegida.
- `resources/views/admin/solicitudes.blade.php`: botón con filtros aplicados.
- `resources/views/admin/reportes/index.blade.php`: enlace al listado correspondiente.

Creados:

- `app/Services/ConsultaSolicitudesInscripcion.php`
- `resources/views/admin/reportes/pdf/inscripciones.blade.php`
- `resources/images/reportes/escudo-inscripciones.jpg`
- `tests/Feature/ReporteInscripcionesPdfTest.php`
- `docs/reporte-inscripciones-pdf.md`

## Pruebas y muestra real

```powershell
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'ReporteInscripcionesPdfTest|SolicitudesListadoTest|ReporteCursosPdfTest|ReportesAdministrativosTest' --do-not-cache-result
```

Resultado final: **46 pruebas aprobadas, 575 aserciones**, 47,457 segundos. Incluye ocho pruebas nuevas y regresión de Solicitudes, Cursos PDF y Reportes. Se comprueban descarga de PDF real, nombre, vacío, caché privada, roles, invitado, parámetros inválidos, métodos rechazados, todos los estados actuales, resumen, búsqueda literal, filtros compartidos con pantalla, ausencia de atribuciones falsas, auditoría de reactivación, escape HTML, exclusión de JSON del encargado, textos extensos, 41 registros en varias páginas y consultas de solo lectura. El bootstrap fuerza SQLite en memoria y almacenamiento temporal; se comprueba el PDO antes de preparar el esquema de pruebas. No se ejecutaron migraciones contra MySQL.

Se comprobó sintaxis PHP en ambos controladores, servicio y prueba. `git diff --check` no informó errores de whitespace. `route:list --path=admin/reportes --json` confirmó los middleware de la descarga nueva.

La muestra se obtuvo de una transacción MySQL **READ ONLY**, finalizada con rollback, mediante el mismo método de descarga del controlador y un administrador existente, sin crear usuarios ni sesiones persistentes. Los conteos se contrastaron con una consulta agrupada independiente:

| Total | Pendientes | Aprobadas | Rechazadas | Inactivas | Otros |
| --- | --- | --- | --- | --- | --- |
| 8 | 1 | 6 | 0 | 1 | 0 |

El PDF final tiene dos páginas y 1.068.952 bytes. Se rasterizaron ambas páginas con Windows.Data.Pdf y se inspeccionaron: logotipos y textos legibles, columnas dentro del área imprimible, encabezado repetido, reactivación con responsable real y sin JSON del encargado. La revisión no incluyó navegación gráfica autenticada; los permisos y la descarga se probaron mediante HTTP automatizado.

Artefactos privados locales:

- `storage/app/reportes-referencia/inscripciones-datos-reales.pdf`
- `storage/app/reportes-referencia/inscripciones-datos-reales-1.png`
- `storage/app/reportes-referencia/inscripciones-datos-reales-2.png`

El verificador temporal fue retirado. No se modificaron datos ni estructura de la base real, ni se ejecutaron comandos destructivos, commits o push. Los cambios previos de Cursos y los Word del usuario se conservaron.

# Estado de Pagos y Mensualidades — PDF

Entrega del 21 de octubre de 2026. Alcance: únicamente Pagos y Mensualidades. No se implementó Control de Asistencia.

## Plantilla y presentación

Referencia obligatoria examinada: `referencias-reportes/Reporte Estado de Pagos y Mensualidades ITM.docx`. Se leyeron sus textos, tablas, medidas de sección, imágenes y pie. Se conservan carta vertical, márgenes superior 43,2 pt, laterales 46,8 pt e inferior 39,6 pt, título de 21 pt, subtítulo, dos logotipos, metadatos de cuatro columnas, resumen de cuatro indicadores, detalle de siete columnas y pie «ITM Aguilares • Consulta de pagos • Gestión Académica», con numeración de páginas. Se eliminaron las instrucciones y ejemplos internos del Word.

Los logotipos son idénticos byte por byte a los ya extraídos para los informes anteriores: se reutilizan `itm.jpg` y `escudo-inscripciones.jpg`. No se generan ni modifican imágenes. El PDF utiliza DejaVu Sans y DejaVu Serif en sustitución de las fuentes Office. La cabecera se adapta a varias líneas para conservar legibilidad y evitar superposición.

Limitación: no se dispone de una exportación PDF del Word original para comparación visual directa; Word no pudo automatizarse en este entorno en la revisión previa. La implementación utiliza la estructura y propiedades verificadas del DOCX. Sí se revisaron visualmente las dos páginas del PDF generado.

## Criterios verificados

Se revisaron Mensualidad, Pago, los cobros y reversiones de MensualidadController, el índice único por `pagos.mensualidad_id`, el módulo de reportes y sus filtros. El sistema registra el monto completo de la mensualidad; no ofrece pagos parciales. Revertir elimina el pago asociado y devuelve la mensualidad a Pendiente. No queda una marca persistente de reversión; no puede reconstruirse un historial de pagos revertidos desde esos registros eliminados.

- Una fila del detalle representa una mensualidad existente. No se une directamente a pagos para sumar obligaciones ni se multiplican filas.
- **Alumnos evaluados:** alumnos únicos del conjunto filtrado, aunque tengan varias mensualidades o cursos.
- **Pagos registrados:** cantidad de registros de pago existentes asociados a mensualidades con estado Pagado. Los movimientos residuales de mensualidades Pendiente se excluyen. Los pagos eliminados al revertir no pueden contarse.
- **Pagos pendientes:** cantidad de mensualidades con estado Pendiente, no monto ni cantidad de movimientos.
- **Monto pendiente:** suma del monto de esas mensualidades, en USD.
- Las columnas **Pagado/Pendiente** muestran el monto de la obligación según su estado actual, igual que el reporte en pantalla. El documento indica que estos importes no son conciliación de caja. Las sumas del PDF se acumulan como centavos enteros.
- Se detectan mensualidades Pagado sin pago, con más de un movimiento o con importe diferente, y mensualidades Pendiente con pago residual. El PDF informa su cantidad sin corregir datos ni inventar pagos parciales. El total de pagos registrados cuenta los movimientos existentes, no confirma su conciliación.
- El esquema vigente impide duplicar pagos para una mensualidad. Las pruebas comprueban esa restricción sin retirar índices.
- Se conservan obligaciones históricas: no se exige matrícula actual ni se inventan mensualidades para alumnos sin deuda registrada.
- El responsable es el nombre real del administrador autenticado. Destinatario institucional: Dirección. El período corresponde al año aplicado y al mes opcional. En pantalla y descarga, omitir el año aplica el año actual del servidor Laravel; omitir el mes consulta todos los meses de ese año.

## Integración y archivos

Pantalla: `/admin/reportes/mensualidades`, botón **Descargar PDF**.

Descarga: `GET /admin/reportes/mensualidades/pdf`, nombre `admin.reportes.mensualidades.pdf`. Se verificaron los middleware `web`, `auth`, `role:admin`. Respuesta `application/pdf`, privada y sin caché. Nombre `estado-pagos-mensualidades-YYYY-MM-DD-HHMMSS.pdf`, con fecha/hora de El Salvador. No se publica el documento en `public`, no se crean facturas y Dompdf no carga recursos remotos.

Se reutilizan exactamente la validación y consulta de filtros del módulo: curso, estado Pendiente/Pagado, año y mes. El mes admite nombre, número y número con cero inicial. Se conserva la paginación de pantalla; la exportación abarca todos los resultados filtrados.

Modificados en esta entrega:

- `app/Services/ReportesAdministrativos.php`: consulta compartida de mensualidades y preparación del PDF.
- `app/Http/Controllers/Admin/ReporteController.php`: descarga y pie institucional.
- `routes/admin.php`: ruta GET protegida.
- `resources/views/admin/reportes/index.blade.php`: botón de Mensualidades.

Creados:

- `resources/views/admin/reportes/pdf/mensualidades.blade.php`
- `tests/Feature/ReporteMensualidadesPdfTest.php`
- `docs/reporte-mensualidades-pdf.md`

Los informes anteriores y los flujos de cobro/reversión se conservan.

## Verificaciones

```powershell
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'ReporteMensualidadesPdfTest|ReportesAdministrativosTest|MensualidadesPanelTest|PagoTest|ReporteCursosPdfTest|ReporteInscripcionesPdfTest|ReporteMaestrosPdfTest' --do-not-cache-result
```

Las siete pruebas nuevas cubren descarga PDF real, nombre, vacío, privacidad, invitado y otros roles, parámetros inválidos, métodos rechazados, alumnos únicos, centavos, restricción de duplicados, reversión mediante el controlador real sobre SQLite, exclusión de pagos residuales, discrepancias sin inferir pagos parciales, tres representaciones del mes, correspondencia con la pantalla, filtros del enlace, escape HTML, consultas de solo lectura y 45 mensualidades en varias páginas. Se ejecutan también pruebas de regresión del panel y de los tres PDF anteriores. El bootstrap fuerza SQLite en memoria y almacenamiento temporal; se comprueba el PDO antes de preparar el esquema de pruebas. No se ejecutan migraciones sobre MySQL.

Resultado final: **70 pruebas aprobadas, 861 aserciones**, 61,474 segundos. La primera ejecución detectó únicamente una comparación del orden de parámetros de URL en una prueba; se corrigió para comparar sus valores independientemente del orden y se repitió la suite completa satisfactoriamente.

Sintaxis PHP de servicio, controlador y prueba comprobada; `git diff --check` sin errores de whitespace; ruta y permisos verificados mediante `route:list`.

## Muestra con datos reales

Se generó mediante el mismo método de descarga, usando un administrador existente, dentro de una transacción MySQL **READ ONLY**, finalizada con rollback. Los totales se contrastaron con consultas independientes:

| Alumnos únicos | Pagos registrados | Mensualidades pendientes | Monto pendiente | Monto marcado pagado | Discrepancias |
| --- | --- | --- | --- | --- | --- |
| 6 | 2 | 9 | $225.00 | $50.00 | 0 |

El documento contiene 11 mensualidades en dos páginas, con 1.326.222 bytes. Se rasterizaron y revisaron ambas páginas mediante Windows.Data.Pdf: logotipos, metadatos, resumen, filas y cantidades legibles, encabezados repetidos, sin columnas desbordadas ni páginas vacías y pie institucional numerado. No se realizó navegación gráfica autenticada; descarga y permisos se comprobaron mediante HTTP automatizado.

Artefactos privados locales:

- `storage/app/reportes-referencia/mensualidades-datos-reales.pdf`
- `storage/app/reportes-referencia/mensualidades-datos-reales-1.png`
- `storage/app/reportes-referencia/mensualidades-datos-reales-2.png`

El verificador temporal se retiró. No se modificaron datos ni estructura de la base real, ni se ejecutaron comandos destructivos, commits o push.


## Año actual predeterminado en Reportes

`ReporteController::filtros()` normaliza el año para tabla y PDF: si no se envía `anio`, aplica `now()->year`, respetando la fecha y zona horaria configuradas en Laravel. No hay un año fijo en producción. Un valor enviado explícitamente se valida y conserva; vacío, texto, arreglos o valores fuera del rango existente 1900–2100 se rechazan. No se transforman en consultas sin año.

El campo Año es obligatorio y editable. Se conservan Curso, Estado y Mes. Todos los meses consulta el año completo; un mes específico se limita al mes y año aplicados. Limpiar abre la ruta sin parámetros y restaura el año actual, todos los cursos, todos los estados y todos los meses.

La paginación y la descarga conservan año, mes, curso y estado. Los indicadores abarcan todas las obligaciones filtradas, no solo la página visible. Se reutilizan sin cambios los cálculos existentes de monto pendiente y monto marcado pagado; no se modifica la conciliación ni el tratamiento de pagos y reversiones.

El mensaje vacío de pantalla identifica el año y, cuando corresponda, el mes consultado. Los cambios de la vista parcial quedan limitados a Mensualidades y a su campo compartido de año, que Alumnos inscritos ya tenía obligatorio. No se modifican plantillas PDF, servicios de cálculo, rutas, cobros, pagos ni el historial por alumno.

Archivos de este ajuste:

```text
app/Http/Controllers/Admin/ReporteController.php
resources/views/admin/reportes/partials/contenido.blade.php
tests/Feature/MensualidadesReporteAnioTest.php
tests/Feature/ReportesAdministrativosTest.php
tests/Feature/AlumnosReporteAnioTest.php
docs/reporte-mensualidades-pdf.md
```

La comprobación de aislamiento de `AlumnosReporteAnioTest` pasa a verificar Maestros sin filtros de año: Mensualidades ya incorpora por autorización su propio año predeterminado. No se cambia el comportamiento del reporte de Alumnos.

Las pruebas usan SQLite en memoria y almacenamiento temporal mediante `tests/documentos-isolated-bootstrap.php`. Cubren el cambio de calendario con tiempo simulado, años anteriores, meses almacenados en sus formatos existentes, totales filtrados, paginación, PDF completo, Limpiar, vacíos, validaciones y permisos. No se modifican registros reales ni se ejecutan migraciones sobre la base real; no se hace commit ni push.

Resultado final de este ajuste: **46 pruebas aprobadas, 727 aserciones**, sin fallos, en una sola ejecución de `MensualidadesReporteAnioTest`, `ReporteMensualidadesPdfTest`, `ReportesAdministrativosTest` y `AlumnosReporteAnioTest`.

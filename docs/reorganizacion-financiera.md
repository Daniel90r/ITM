# Organización de Mensualidades y Pagos

Mensualidades mantiene generación, consulta de cuotas y todos los controles de cobro individual, lote y reversión. Pagos es exclusivamente de consulta. Sus endpoints POST y lógica financiera se conservan en Mensualidades; no hay migraciones ni cambios en datos reales.

## Navegación

- `/admin/pagos`, `admin.pagos`: movimientos reales de la tabla pagos.
- `/admin/pagos/historial`, `admin.pagos.historial.index`: selector de alumnos para consultar su estado de cuenta. El parámetro antiguo registrar no habilita controles de escritura ni se propaga.
- `/admin/pagos/alumnos/{alumno}/estado-cuenta`, `admin.pagos.estado-cuenta`: cuotas del año y movimientos con fecha de pago en ese año, en secciones diferenciadas.
- Las dos rutas históricas bajo Mensualidades conservan sus nombres y responden 302 hacia Pagos, preservando los parámetros. Todos los destinos mantienen auth y role:admin.

La elegibilidad del estado de cuenta sigue siendo inscripción aprobada o mensualidades existentes en cualquier año. No depende del estado institucional activo. Los pagos históricos se consultan aunque no exista inscripción vigente. Una solicitud pendiente no habilita generación.

## Movimientos

ConsultaPagos comparte el criterio entre el listado general y el estado de cuenta. Usa joins contra claves primarias, sin unir inscripciones ni realizar consultas por fila. El curso procede exclusivamente de mensualidades.curso_id; un origen desconocido se identifica sin atribuirle el curso actual.

Los filtros alumno_id, q, anio, mes y page se validan en servidor. Año y mes corresponden a fecha_pago; si no se selecciona año, se conservan los pagos de todos los años. El mes y año de la cuota se muestran aparte. Paginación de 25 movimientos, orden estable por fecha e ID y sumas sobre todas las páginas filtradas. Los indicadores del mes actual comparan mes y año.

El estado de cuenta presenta total pagado por movimientos con fecha de pago en el año seleccionado, saldo pendiente y vencido de las obligaciones de ese año. Se conserva además el importe de cuotas marcadas pagadas como información diferenciada. Una cuota sin registro no genera deuda. No hay formularios de cobro, casillas, registro por lote ni reversión en las pantallas de Pagos; el cierre de sesión compartido no es una operación financiera.

Las cuotas marcadas pagadas no sustituyen los registros de pagos. Un pago existente cuya cuota no figura pagada se muestra con advertencia, sin corregirlo automáticamente.

## Compatibilidad y límites

Se mantienen los métodos pagar, pagarLote y revertir y sus redirecciones existentes. En particular, el cobro en lote conserva su retorno a Mensualidades. Las boletas académicas siguen en su módulo; no se presentan como comprobantes financieros. No existen campos de método de pago en los modelos revisados y no se inventan.

La reversión actual elimina el registro de pago: esta tarea no añade un historial de reversiones ni reconstruye movimientos eliminados. No se modifican plantillas PDF.

## Verificación

OrganizacionFinancieraTest cubre movimientos, fechas y años, curso histórico, alumnos retirados, elegibilidad, registros individuales y en lote, validación, permisos, paginación, duplicados, redirecciones y consultas sin N+1. Las regresiones de elegibilidad, historial, panel, pagos, PDF, acceso administrativo y responsividad se ejecutan con SQLite en memoria y almacenamiento temporal. No se ejecuta TransicionDocumentalTest.

La lectura de MySQL 8.4.7 se realizó dentro de una transacción READ ONLY: tres pagos existentes, tres IDs únicos y ningún pago huérfano. No se ejecutaron escrituras sobre itm.

## Archivos de esta reorganización

- Controladores: app/Http/Controllers/Admin/PagoController.php y HistorialMensualidadController.php.
- Consulta nueva: app/Services/ConsultaPagos.php.
- Rutas: routes/admin.php.
- Vistas: resources/views/admin/pagos.blade.php, mensualidades.blade.php, mensualidades-historial-alumnos.blade.php y mensualidades-historial.blade.php.
- Parciales: resources/views/admin/partials/mensualidades-detalle.blade.php, mensualidad-historial-celdas.blade.php, pagos-movimientos.blade.php y pagos-navegacion.blade.php.
- Layout: resources/views/components/historial-mensualidades-layout.blade.php.
- Pruebas: tests/Feature/OrganizacionFinancieraTest.php, ElegibilidadMensualidadesTest.php, HistorialMensualidadesTest.php, MensualidadesPanelTest.php y StaffResponsiveTest.php.
- Documentación: este documento y docs/historial-mensualidades-alumno.md.

Las modificaciones anteriores de otros módulos y del controlador de generación/cobros no forman parte de esta reorganización y se conservaron.

Resultado final: 73 pruebas PHP aprobadas, 1.496 aserciones, sin fallos ni omisiones en una sola ejecución de las ocho clases indicadas. Regresión JavaScript del panel: ocho pruebas aprobadas, ejecución independiente. Chrome: 20 comprobaciones aprobadas de las cuatro pantallas en 320, 375, 390, 768 y 1440 px, con datos sintéticos y capturas fuera del repositorio.

## Corrección definitiva: Pagos solo consulta

Se retiran de Pagos el modo registrar, la navegación de registro, formularios de pago o reversión, selección de cuotas y cobro en lote. Mensualidades recupera sus formularios individuales, casillas por cuota y por curso y barra de cobro por lote, con los mismos endpoints, CSRF y confirmaciones JavaScript. No se modifican los métodos de generación, registro o reversión ni las rutas.

Archivos de esta corrección:

- app/Http/Controllers/Admin/HistorialMensualidadController.php.
- resources/views/admin/mensualidades.blade.php, mensualidades-historial.blade.php y mensualidades-historial-alumnos.blade.php.
- resources/views/admin/partials/pagos-navegacion.blade.php, mensualidad-historial-celdas.blade.php y mensualidades-detalle.blade.php.
- tests/Feature/OrganizacionFinancieraTest.php y MensualidadesPanelTest.php.
- docs/reorganizacion-financiera.md.

La prueba de solo consulta revisa las tres pantallas, incluso con registrar=1, y admite únicamente el POST de cierre de sesión compartido. Verifica ausencia de controles financieros de escritura y conserva el historial de retirados. Las pruebas de registro comprueban los controles de Mensualidades y ejecutan los endpoints existentes exclusivamente sobre datos sintéticos.

Verificación final de la corrección: 74 pruebas PHP aprobadas, 1.522 aserciones, sin fallos ni omisiones en una ejecución aislada. JavaScript: ocho pruebas aprobadas en ejecución independiente. Chrome: 20 comprobaciones aprobadas en 320, 375, 390, 768 y 1440 px; se confirmó ausencia de controles financieros de escritura en Pagos. No se modificaron datos reales ni se ejecutó TransicionDocumentalTest.

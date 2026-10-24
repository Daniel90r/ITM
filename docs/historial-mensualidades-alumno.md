# Historial anual de mensualidades por alumno

Acceso desde Pagos: «Estado de cuenta por alumno» abre un buscador paginado que incluye alumnos activos e inactivos que tengan alguna inscripción en estado aprobada o mensualidades registradas. Una solicitud pendiente o rechazada, por sí sola, no habilita esta consulta. Mensualidades enlaza a «Ver estado de cuenta en Pagos», conservando el año seleccionado. Los detalles de navegación y movimientos se documentan en reorganizacion-financiera.md.

Rutas GET protegidas por `auth` y `role:admin`:

- `/admin/pagos/historial`: `admin.pagos.historial.index`.
- `/admin/pagos/alumnos/{alumno}/estado-cuenta`: `admin.pagos.estado-cuenta`.
- Las rutas y nombres antiguos de historial bajo Mensualidades se conservan como redirecciones 302 con sus parámetros.

La página consulta `mensualidades`, no un listado exclusivo de cobros. Los meses sin registro se limitan por una referencia de visualización cuando está disponible; si no puede determinarse, se conservan enero a diciembre. No genera obligaciones ni infiere el comienzo del cobro. Los meses sin registro no tienen importe ni forman parte del saldo.

La pantalla solo ofrece **Año** y **Consultar**. El curso asignado aparece junto al alumno, como información: se obtiene de una única inscripción aprobada y activa. Si no existe, se indica que no hay curso activo; si hay varias, se señala la inconsistencia sin escoger una arbitrariamente. Esto no limita la consulta económica.

Se incluyen todas las obligaciones del alumno para el año, sin filtro por curso, incluso de cursos anteriores o sin curso asociado. Los parámetros antiguos `curso_id` se ignoran expresamente: un enlace guardado no puede ocultar obligaciones. Cada fila conserva el curso real de `mensualidades.curso_id` y el botón de regreso sigue disponible.

## Fecha de referencia de visualización

Se utiliza `inscripciones.fecha_inscripcion` como **Fecha registrada de solicitud/inscripción**. Puede corresponder a una solicitud pendiente o reenviada: no acredita aprobación ni inicio obligatorio de cobro. No se utiliza `updated_at`, la fecha actual de otra inscripción, la reactivación ni el primer pago para reconstruir una fecha.

- La referencia es global del alumno: se exige una única fecha válida compartida por sus solicitudes/inscripciones, independientemente de su estado, y que los cursos con obligaciones tengan inscripción identificable.
- Si hay fechas históricas diferentes, obligaciones sin curso asociado o sin inscripción del curso correspondiente, se conserva el calendario completo con una explicación breve. No se utiliza la fecha del curso actual para recortar obligaciones de otro curso.
- Una fecha vacía, inexistente o inválida (incluidas fechas que se normalizarían a otro día) mantiene los 12 meses y un aviso. Se valida estrictamente el formato y la fecha de calendario.
- Con referencia válida, los meses vacíos empiezan en su mes y terminan en el mes actual de El Salvador. Para años anteriores al actual llegan hasta diciembre; los años anteriores a la referencia no incorporan meses vacíos. Los años futuros no incorporan meses vacíos.
- Las obligaciones reales prevalecen siempre: se agregan sus meses al intervalo aunque sean anteriores a la referencia o posteriores al mes actual. Los registros con mes no reconocido siguen en su sección separada.
- Solo cambia la selección de meses sin registros; los indicadores se calculan antes de aplicar el intervalo y conservan los criterios financieros anteriores.

## Criterios

- Año entre 1900 y 2100, por defecto el actual de El Salvador.
- Curso de cada obligación según `mensualidades.curso_id`, independiente de la inscripción actual. Los registros con curso nulo se identifican como «Sin curso asociado»; no existe selector de curso.
- Pagado y Pendiente mantienen el estado almacenado. Vencido es solo la presentación de una pendiente cuyo vencimiento es anterior al día actual en `America/El_Salvador`. Vencer hoy no significa estar vencida.
- El saldo pendiente incluye el importe vencido; ambos indicadores no se suman entre sí. Los importes se acumulan en centavos, por ID de obligación, sin unir tablas que multipliquen filas.
- Los nombres de meses y representaciones numéricas se normalizan en memoria. Varias obligaciones del mismo alumno, año y mes se señalan como posibles duplicados, incluso si pertenecen a cursos distintos. No se decide automáticamente si corresponde cobrar por alumno o por curso.
- Todos los registros se mantienen visibles. Los grupos ambiguos se excluyen de los tres indicadores, y su importe queda señalado para revisión. No se elige ni se elimina uno arbitrariamente. Se consideran todas las obligaciones del alumno en el año, sin separar ni duplicar el saldo por cursos.
- Las inconsistencias entre estado y pagos se señalan sin corregir estados ni movimientos. Los importes sin duplicados siguen su estado almacenado, igual que el reporte existente. Un movimiento residual de una pendiente no se presenta como fecha de pago válida.
- Meses no reconocidos se muestran en una sección separada, sin asignarles un mes ficticio. Sus importes no duplicados conservan el tratamiento por estado y llevan una advertencia.

La vista es de consulta: no contiene acciones de cobro, generación, reversión ni modificación. Los cálculos y el PDF permanecen sin cambios. El listado y el acceso directo al historial comparten el mismo criterio de elegibilidad. Las obligaciones de años anteriores permiten consultar al alumno aunque actualmente no tenga inscripción aprobada.

## Pruebas aisladas

```powershell
php -d xdebug.mode=off -d memory_limit=768M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'HistorialMensualidadesTest|MensualidadesPanelTest|PagoTest|ReporteMensualidadesPdfTest|AdminGlobalRoleAccessTest' --do-not-cache-result
```

El bootstrap fuerza SQLite en memoria, comprueba el controlador PDO y utiliza almacenamiento temporal. Las pruebas cubren los 12 meses, vacíos, fechas y vencimiento del día, centavos, históricos sin inscripción vigente, cursos inactivos, curso nulo, filtros, duplicados normalizados, estados/movimientos inconsistentes, meses inválidos, consultas de lectura, permisos y búsqueda paginada. Las otras clases verifican la regresión de panel, pagos y PDF.

Resultado de la primera fase: **43 pruebas aprobadas, 836 aserciones, cero fallos y omisiones**, en una única ejecución de las cinco clases indicadas.

Resultado final del ajuste de fecha de referencia: **47 pruebas aprobadas, 898 aserciones, cero fallos y omisiones**, en una única ejecución de las mismas cinco clases. Las pruebas adicionales cubren referencia reciente, inscripción inactiva, cursos separados y referencia global, fechas inválidas o ausentes, años anteriores y futuros, obligaciones fuera del intervalo y origen desconocido. Sintaxis PHP y comprobación de diferencias satisfactorias. No se consultó ni modificó la base real durante esta implementación, ni se ejecutaron comandos de migración contra ella.

Resultado de la simplificación a consulta anual sin selector de curso: **49 pruebas aprobadas, 984 aserciones**, sin fallos ni omisiones, en una sola ejecución de las cinco clases. Se verifican la ausencia del selector, el curso asignado informativo, las asignaciones ambiguas, fechas históricas distintas y la conservación de obligaciones, pagos y saldos ante parámetros antiguos de curso, incluidos registros sin curso. Se mantiene la regresión de pagos y PDF. Todas las pruebas utilizan SQLite en memoria y almacenamiento temporal.

## Elegibilidad del historial y generación

El listado y la ruta individual comparten una consulta Eloquent: alguna inscripción con estado `aprobada`, o alguna mensualidad existente en cualquier año. No se utiliza `alumnos.activo` como sustituto de aprobación. Las condiciones se agrupan antes de aplicar la búsqueda y no se unen filas de inscripciones, evitando duplicar alumnos. Un expediente sin aprobación ni obligaciones no aparece y su URL individual responde 404.

Las solicitudes pendientes, rechazadas e inactivas no habilitan nuevas mensualidades. La generación conserva su consulta existente de inscripciones aprobadas y su deduplicación por alumno; ahora rechaza explícitamente la selección sin aprobados mediante validación, en lugar de mostrar éxito con cero generaciones. El acceso al historial por obligaciones anteriores no habilita generación. No se cambian importes, vencimientos, pagos, estados almacenados ni el PDF.

Los escenarios de referencia de expedientes retirados en las pruebas incorporan una obligación sintética de otro año para acreditar un historial económico real. Esa obligación no modifica los totales del año consultado. Las pruebas de esta corrección están en ElegibilidadMensualidadesTest, junto a las regresiones existentes de historial, panel, pagos, PDF y permisos.

Verificación final de elegibilidad: 64 pruebas aprobadas, 1.185 aserciones, cero fallos y omisiones en una sola ejecución de ElegibilidadMensualidadesTest, HistorialMensualidadesTest, MensualidadesPanelTest, PagoTest, ReporteMensualidadesPdfTest y AdminGlobalRoleAccessTest. SQLite en memoria y almacenamiento temporal; sin ejecutar TransicionDocumentalTest. La comprobación del caso reportado en MySQL fue exclusivamente de lectura.

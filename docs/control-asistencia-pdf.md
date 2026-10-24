# Control de Asistencia institucional — PDF

## Modalidad histórica filtrada por estado

La descarga existente `admin.asistencias.pdf` acepta `estado` vacío (control completo sin cambios), `presente`, `ausente` o `permiso`. Se rechazan estados no válidos. Se mantienen curso y materia obligatorios, su validación de pertenencia y los permisos de administrador.

`FiltrosHistoricosAsistencia` centraliza curso, asignación de materia, estado y fechas inclusivas para `ReportesAdministrativos` y la consulta del controlador. `AsistenciaHistoricaPdf` usa esa consulta sin exigir inscripción vigente ni asignación opcional actual: incluye exclusivamente registros históricos reales coincidentes. Identifica registros únicos por ID, no crea alumnos ni fechas y obtiene los nombres de registradores de cada asistencia.

La nueva vista `asistencia-filtrada.blade.php` reutiliza estilos, encabezado, logotipos y sección académica del control institucional mediante parciales. El encabezado y el pie identifican el estado y la modalidad histórica. La tabla muestra fecha, alumno, materia, estado y registrador; las observaciones se conservan en anexo. Los totales son cantidades de registros incluidos por estado, nunca la nómina actual ni una conciliación con marcas inexistentes. El resultado vacío conserva el encabezado y un mensaje explícito. La paginación no limita la cantidad total exportada.

El horario mostrado sigue siendo la configuración actual, expresamente identificada. Los nombres de responsables proceden de `registrado_por`, sin atribuir registros antiguos al docente actual. Los documentos completos mantienen la matriz, leyenda, cálculos y alcance previamente documentados.

La interfaz conserva todos los filtros y habilita la descarga con cualquier estado válido una vez aplicados curso y materia. Continúa invalidando el enlace anterior si se editan filtros sin aplicarlos. El archivo filtrado se denomina `asistencias-historicas-{estado}-{curso}-{detalle}-{fecha}.pdf`.

Se añaden pruebas de las cuatro modalidades, coincidencia con el listado, históricos sin matrícula ni asignación opcional, estado inválido, fechas, curso/materia, roles, totales, unicidad, vacío, paginación y observaciones. La muestra opcional `asistencia-historica-ausente-prueba.pdf` contiene fixtures sintéticos y se guarda fuera del disco público.

Resultado final de esta ampliación: **85 pruebas PHP aprobadas, 6.782 aserciones**, sin fallos ni omisiones, en una única ejecución con `tests/sqlite-memory-bootstrap.php` y las siete clases de reportes y asistencia. Por separado, las **3 pruebas JavaScript** de selección dependiente e invalidación de descarga pasaron. La muestra filtrada tiene cuatro páginas (21 registros de ausencia y un anexo), se rasterizó con Windows.Data.Pdf y se revisó visualmente. No se ejecutaron migraciones ni operaciones sobre la base real.

## Actualización visual institucional

Se revisó de nuevo `referencias-reportes/Asistencia.docx`, incluidos sus textos y medios. Los SHA-256 de sus dos imágenes coinciden con los recursos reutilizados: `itm.jpg` y `escudo-inscripciones.jpg`. El logotipo identifica al **Instituto de Computación de Aguilares**; la plantilla denomina los cargos **Director** e **Instructor**. No se incorporaron nombres ni firmas del ejemplo Word.

El diseño actualizado mantiene carta horizontal, logotipos con proporciones originales, encabezado institucional repetido, fecha de emisión, sección académica compacta, mes determinado por fechas registradas, período consultado, leyenda y tabla. Se diferencian visualmente los totales por grupo, fecha y período; no cambian sus cálculos. Se conservan nombres completos, observaciones y advertencias de registros que requieren revisión. Las observaciones extensas pasan al anexo, también con encabezado y pie institucional.

La presentación conserva los bloques del servicio (hasta 12 fechas y 10 alumnos). Solo en Blade, los grupos con nombres de más de 60 caracteres se subdividen en hasta 6 alumnos; si superan 120, en hasta 3. Esta distribución evita abreviar nombres y separar totales o firmas. No cambia la consulta, la nómina ni las marcas del servicio.

La muestra `storage/app/reportes-referencia/asistencia-diseno-prueba.pdf` utiliza **exclusivamente fixtures sintéticos**, identificados como prueba: 11 alumnos con nombres largos, 13 fechas, 5 presentes, ausencias y permisos, alumnos sin registro y seis observaciones extensas. Tiene siete páginas: seis grupos de matriz y un anexo. No reemplaza la muestra histórica con datos reales documentada más abajo.

La prueba de presentación comprueba el número de páginas, nombres, cargos, advertencia y posición de las cajas de texto dentro de la página. También se mantiene la prueba existente de seis páginas con 19 alumnos y 25 fechas. El PDF de prueba se exporta únicamente al definir `ITM_ASISTENCIA_VISUAL=1` durante las pruebas aisladas.

Para generar imágenes de revisión con Windows.Data.Pdf:

```powershell
powershell -NoProfile -File tests/Frontend/render-asistencia-pdf.ps1 -Pdf C:/wamp64/www/ITM/storage/app/reportes-referencia/asistencia-diseno-prueba.pdf -OutputPrefix C:/wamp64/www/ITM/storage/app/reportes-referencia/asistencia-diseno-prueba
```

La mejora se limita a la vista de este PDF, sus pruebas y documentación. Los filtros, permisos, controlador, servicio de cálculos, base de datos y los otros PDF permanecen sin cambios en esta actualización.

Validación final de la actualización: **79 pruebas aprobadas, 6.662 aserciones**, sin fallos ni omisiones, en una sola ejecución aislada de `ControlAsistenciaPdfTest`, `AdminAsistenciasReporteTest`, `ReportesAdministrativosTest` y las cuatro clases `Reporte*PdfTest`. La muestra de siete páginas fue rasterizada e inspeccionada; se comprobaron nombres completos, columnas, totales, firmas, anexo y encabezados repetidos, sin cortes observados. Sintaxis PHP y `git diff --check` satisfactorios.

Entrega del 21 de octubre de 2026. Alcance exclusivo de esta entrega: Control de Asistencia. Los otros cuatro informes no se modificaron.

## Inspección y plantilla

Se revisaron Alumno, Inscripcion, Curso, CursoHorario, Materia, DetalleCurso, AsignacionDocente, Asistencia, los controladores administrativos y docentes de asistencia, `ResuelveMateriasDelDocente`, `HorariosCurso`, filtros, rutas y permisos. Se leyó `referencias-reportes/Asistencia.docx` como OOXML: encabezado, imágenes, metadatos, matriz, totales, observaciones y tabla de firmas.

El Word está definido como carta **vertical**; se aplica carta **horizontal** por instrucción expresa del usuario. Se conservan título, dos logotipos, curso/docente/horario/período, matriz con fechas, total de alumnos y fechas, observaciones y espacios de firma de director e instructor. Se añade materia para delimitar la unidad académica y numeración de páginas. Los logotipos son idénticos byte por byte a los ya extraídos para los otros informes; se reutilizan sin editarlos. Se retiraron fechas y nombres del ejemplo, la anotación «ANALIZAR» y los nombres ficticios de firmantes.

No se cuenta con un PDF renderizado del Word para comparación directa: la automatización de Word no había funcionado en este entorno. La similitud se verificó contra la estructura y propiedades del DOCX, y la presentación del PDF generado se revisó visualmente. Se usa DejaVu Sans para los caracteres españoles.

## Reglas de información

- La unidad del documento es **un curso y un detalle de curso (materia)**. Ambos son obligatorios y deben corresponderse. No se incluyen registros generales con `detalle_curso_id` nulo ni de otra materia/curso.
- Solo el administrador puede descargar este nuevo PDF. No se amplían los permisos de los docentes ni se modifican sus rutas existentes.
- Se reutiliza la consulta filtrada del módulo administrativo: curso, materia, docente actual del detalle, fechas inclusivas y búsqueda literal por nombre/apellido/código. Se rechaza un docente incompatible con la materia y un período invertido.
- Se reutiliza la regla real `alumnosDeLaMateria`: inscripción con estado **aprobada**, alumnos únicos, y vínculo alumno/curso/materia si la materia es opcional. No se reemplaza ese criterio por el campo `activa` ni por la visibilidad aislada del alumno. Las solicitudes pendientes, rechazadas e inactivas quedan fuera.
- El listado es de **inscripciones aprobadas actuales**. El sistema no permite reconstruir una nómina histórica a una fecha; se aclara este alcance en el documento. Por ello puede diferir del historial general administrativo, que también contiene alumnos sin inscripción vigente.
- Las fechas de columnas proceden únicamente de registros existentes de esa materia y alumnos incluidos, dentro del período. No se generan sábados/domingos ni ausencias implícitas. El horario del curso es configuración semanal actual, no calendario histórico por materia.
- Estados reales: `presente`, `ausente`, `permiso`. Se muestran P, A y PM. No existe estado separado «justificado»; no se renombra permiso como justificación. Un guion significa sin registro. La marca ? señala una celda inconsistente, sin inventar un estado académico.
- Solo P suma asistencia. Se calculan totales por alumno, fecha, bloque y período. Las celdas duplicadas se señalan y excluyen del cómputo; el esquema vigente impide repetir alumno/materia/fecha. Se eliminan repeticiones de alumnos y columnas por identidad, sin mezclar materias.
- Docente: nombres de quienes registraron la asistencia, mediante `registrado_por`. Si no existen registros, solo se muestra la asignación actual cuando es única y coincide con el detalle, identificándola como tal. No se atribuyen registros históricos a un docente nuevo.
- Horario: `HorariosCurso::lineas`, o «Horario no configurado». Las firmas quedan como espacios para completar; no se insertan firmas digitales ni nombres históricos no verificados.
- Observaciones: textos reales de las asistencias incluidas, identificados por alumno y fecha, escapados por Blade. No se exportan DUI, teléfonos, direcciones ni expedientes.

## Paginación e integración

La matriz se divide en bloques de hasta **12 fechas y 10 alumnos** para conservar legibilidad. Cada bloque repite identidad, metadatos, encabezados, leyenda, criterios y firmas. Se distinguen los totales del grupo de alumnos y los totales globales por fecha; el total del período no debe sumarse repetidamente entre bloques. Las observaciones breves aparecen al final de la matriz; las extensas pasan a un anexo. El vacío conserva el formato y explica que no se infieren ausencias.

Botón **Descargar PDF** en `/admin/asistencias` al seleccionar curso y materia. Antes de seleccionarlos, la pantalla indica el requisito. El enlace conserva los filtros aplicados y exporta el conjunto completo, sin limitarse a la paginación de pantalla.

Ruta nueva: `GET /admin/asistencias/pdf`, nombre `admin.asistencias.pdf`, middleware comprobado `web`, `auth`, `role:admin`. Archivo `control-asistencia-{curso_id}-{detalle_curso_id}-YYYY-MM-DD-HHMMSS.pdf`, fecha/hora de El Salvador. Dompdf en horizontal, recursos locales embebidos, acceso remoto desactivado, respuesta `application/pdf` privada y sin caché. No se guarda en una ruta pública.

## Archivos de esta entrega

Modificados:

- `app/Http/Controllers/Admin/AsistenciaController.php`: método PDF; se conservan registro, listado y detalle actuales.
- `routes/admin.php`: ruta administrativa GET.
- `resources/views/admin/asistencias.blade.php`: botón e indicación para seleccionar curso/materia.

Creados:

- `app/Services/ControlAsistenciaPdf.php`
- `resources/views/admin/reportes/pdf/asistencia.blade.php`
- `tests/Feature/ControlAsistenciaPdfTest.php`
- `docs/control-asistencia-pdf.md`

## Verificaciones

```powershell
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'ControlAsistenciaPdfTest|AdminAsistenciasReporteTest|DocenteAsistenciaMateriaTest|ReporteCursosPdfTest|ReporteInscripcionesPdfTest|ReporteMaestrosPdfTest|ReporteMensualidadesPdfTest' --do-not-cache-result
```

Resultado final: **66 pruebas aprobadas, 697 aserciones, cero fallos y cero errores**, 62,315 segundos.

Las seis pruebas nuevas comprueban descarga real, nombre, caché privada, vacío, roles e invitado, validación de curso/materia/docente/período, métodos rechazados, inscritos aprobados, materia opcional del curso correcto, P/A/PM/sin registro, exclusión de otra materia y asistencia general, fechas únicas, totales, filtros de período y búsqueda en el controlador, botón, consultas de solo lectura, escape HTML, ausencia de DUI y exactamente seis páginas para 19 alumnos y 25 fechas. Se ejecuta regresión administrativa/docente y de los cuatro informes anteriores. Las primeras ejecuciones detectaron datos de prueba incompletos (registrador obligatorio y valor personal nulo), corregidos sin alterar el modelo de producción. La comprobación de paginación detectó además una distribución uniforme incorrecta de columnas de Dompdf; se corrigió con anchos explícitos para nombres y fechas. El caso de seis páginas pasó individualmente y se revisó visualmente con datos exclusivamente sintéticos, separados de la muestra real. Se retiraron esos artefactos temporales.

El bootstrap fuerza SQLite en memoria y almacenamiento temporal; la prueba comprueba el PDO antes de preparar el esquema. No se ejecutan migraciones sobre MySQL. Se verifican sintaxis PHP, `git diff --check` y middleware de la ruta con `route:list`.

## PDF con datos reales

La muestra se obtuvo mediante el mismo método del controlador dentro de una transacción MySQL **READ ONLY**, finalizada con rollback. Una consulta independiente confirmó el total de presentes.

- Curso: **Curso A**.
- Materia: **Microsoft Word**.
- Inscritos incluidos: **5**.
- Fecha real: **27/09/2026**.
- Registros: **3**, de los cuales **2 presentes y 1 ausente**; otros **2 alumnos sin registro**. No existen permisos en esta muestra y no se inventaron.
- Horario configurado del curso: sábado, 07:30–11:30. No se usa para alterar ni invalidar la fecha histórica de domingo registrada.
- Observación real incluida: «Cambio de estado».

El PDF final tiene una página horizontal, con 1.066.082 bytes. Se rasterizó con Windows.Data.Pdf y se inspeccionó: encabezado y logotipos, nombres y marcas, totales, observación, firmas y numeración legibles, sin desbordamientos ni páginas adicionales vacías. No se realizó navegación gráfica autenticada; los permisos y descarga se verificaron mediante HTTP automatizado.

Artefactos privados locales:

- `storage/app/reportes-referencia/asistencia-datos-reales.pdf`
- `storage/app/reportes-referencia/asistencia-datos-reales-1.png`

Se retiró el verificador temporal. **No se modificaron datos ni estructura de la base real**, no se ejecutaron migraciones destructivas y no se hicieron commits ni push. La tarea se detiene en Control de Asistencia.

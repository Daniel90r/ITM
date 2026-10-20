# ITM — Módulo de reportes administrativos

Implementación y verificación: 19 de octubre de 2026, hora local de El Salvador.

## Alcance y archivos

Se implementaron los seis reportes solicitados, independientes de las fases documentales 2G-2B. No se agregaron dependencias ni migraciones.

Archivos creados:

- `app/Http/Controllers/Admin/ReporteController.php`: acceso a portada, validación por tipo de reporte y respuesta privada sin caché.
- `app/Services/ReportesAdministrativos.php`: consultas de solo lectura, resúmenes y paginación.
- `resources/views/admin/reportes/index.blade.php`: portada, tarjetas, filtros, indicadores, tablas, estados vacíos y menú adaptable.
- `tests/Feature/ReportesAdministrativosTest.php`: 25 pruebas con datos ficticios y SQLite en memoria.
- `public/build/assets/app-XZzwDArQ.css`: CSS compilado con las clases de las vistas actuales.
- Este informe.

Archivos modificados:

- `routes/admin.php`: dos rutas GET dentro del grupo existente `auth` y `role:admin`.
- `resources/views/components/admin-sidebar.blade.php`: enlace Reportes con icono de gráfico y selección activa.
- `public/build/manifest.json`: únicamente la referencia al CSS principal. Se conservaron las entradas JavaScript y los archivos publicados anteriores.

## Auditoría previa y reglas encontradas

Se revisaron las rutas administrativas, `RoleMiddleware`, los modelos Alumno, Inscripcion, Curso, Materia, DetalleCurso, Nota, Asistencia, Maestro, AsignacionDocente, Mensualidad y Pago, sus migraciones y relaciones, el sidebar y el layout `x-app-layout`.

También se revisaron los controladores administrativos de cursos, notas, asistencias, mensualidades, maestros y boletas; el criterio de materias del portal docente; `ResumenCursoAlumno` y `BoletaService`; las pruebas existentes y la configuración de Tailwind/Vite.

Hallazgos que determinan los resultados:

- Una inscripción efectiva tiene `estado = aprobada`, según `Inscripcion::scopeAprobada`. No se confunde con una solicitud pendiente ni con el campo `activa` aislado.
- El estado académico de Curso lo determina `estado_curso`: finalización manual, fecha de fin, estado explícito y fechas configuradas. `activo` es la visibilidad, no un sustituto del estado académico.
- El máximo es `Curso::CUPOS_MAXIMO = 20`. El sistema cuenta filas de inscripciones aprobadas y limita la disponibilidad inferior a cero.
- Nota guarda el promedio redondeado de laboratorio, examen teórico y práctica no nulos. El cero participa y conducta no participa. La consulta del reporte debe leer el valor almacenado.
- Las materias opcionales requieren el vínculo alumno/curso/materia en `alumno_materia`; no basta con un vínculo del mismo alumno en otro curso.
- La fuente de asignaciones vigentes es `asignaciones_docentes.estado = activa`; las programadas y finalizadas son historial. Se comprueban además disponibilidad de maestro/curso/materia, coincidencia con `detalle_cursos.maestro_id` y unicidad de asignación activa antes de presentarla sin advertencia.
- Mensualidad utiliza `Pendiente` y `Pagado`. El panel existente suma su `monto` por estado. Los meses pueden estar guardados como nombre, número o número con cero inicial.
- El esquema vigente impide inscripciones repetidas alumno/curso, notas repetidas alumno/detalle y pagos repetidos por mensualidad mediante índices únicos. Las pruebas comprueban esos límites sin retirar índices ni inventar duplicados admisibles.
- No existía un módulo general de Reportes. Los listados de asistencias y mensualidades proporcionaron criterios reutilizables; no se invocan operaciones de emisión, cobro ni exportación documental.

## Rutas y filtros

Todas requieren sesión de administrador. Admiten GET/HEAD; no hay rutas de escritura del módulo.

| URL | Nombre / reporte | Filtros |
| --- | --- | --- |
| `/admin/reportes` | `admin.reportes`, portada | Selección mediante tarjetas |
| `/admin/reportes/alumnos` | Alumnos inscritos | Curso, estado del alumno activo/inactivo |
| `/admin/reportes/cursos` | Cursos | Curso, estado académico |
| `/admin/reportes/calificaciones` | Calificaciones | Curso, materia |
| `/admin/reportes/asistencias` | Asistencias | Curso, presente/ausente/permiso, desde, hasta |
| `/admin/reportes/mensualidades` | Mensualidades | Curso, Pendiente/Pagado, año, mes |
| `/admin/reportes/maestros` | Maestros | Curso, estado del maestro activo/inactivo |

Los seis detalles usan `admin.reportes.show` con `tipo` restringido a esos seis valores. Los identificadores deben ser enteros positivos y existir; los estados se validan contra listas cerradas. Las fechas son `Y-m-d`, con fin igual o posterior al inicio; el año admite 1900–2100 y el mes 1–12. `page` es un entero positivo limitado. Los filtros válidos se conservan en la paginación; Limpiar regresa al listado completo del tipo actual.

## Cálculo y presentación

| Reporte | Filas e indicadores |
| --- | --- |
| Alumnos | Una fila por alumno con al menos una inscripción aprobada. Se agrupan sus cursos aprobados por ID, incluidos los históricos; el filtro de curso restringe los cursos mostrados. El resumen cuenta alumnos únicos. El estado filtrable es el del alumno: la inscripción mostrada siempre es aprobada. |
| Cursos | Una fila por curso; estado mediante el accesor existente, visibilidad por separado, máximo 20, número de inscripciones aprobadas y `max(0, 20 - aprobadas)`. El resumen cuenta los cursos filtrados. |
| Calificaciones | Una fila de notas por alumno y materia del curso, con los componentes, conducta y promedio almacenados. Exige inscripción aprobada, materia activa y asignación de la opcional en ese curso. No actualiza notas, no sustituye promedios inconsistentes y no emite boletas. El indicador cuenta registros, incluso si tienen componentes sin capturar. |
| Asistencias | Una fila por registro existente, con fecha, alumno, curso, materia y estado. Los extremos del período son inclusivos. Resumen de registros, presentes, ausentes y permisos. No crea días ni ausencias implícitas. |
| Mensualidades | Una fila por obligación existente. Suma una vez su monto por estado, sin unir ni multiplicar filas de pagos. El filtro de mes admite las tres representaciones existentes. Incluye obligaciones históricas; no elimina pendientes porque el alumno ya no esté inscrito. Los indicadores son montos de mensualidades, no conciliación de caja. |
| Maestros | Una fila por maestro, con estado y cursos/materias de sus asignaciones activas. Sin filtro de curso también aparecen maestros sin asignaciones. Señala asignaciones ambiguas o no disponibles; no presenta historial finalizado/programado como vigente. |

Las tablas tienen 25 filas por página y orden estable. Los indicadores abarcan todos los resultados filtrados. Un guion significa dato sin captura; el cero se conserva. Se utilizan relaciones precargadas o joins/EXISTS sin multiplicar filas y sin consultas por cada resultado. Las consultas seleccionan datos de presentación: no se muestran DUI, dirección, teléfono, credenciales, observaciones privadas ni documentos de inscripción.

El diseño conserva Blade, Tailwind, tipografía y colores del panel. Las tarjetas se adaptan al ancho, los filtros se reorganizan y las tablas tienen desplazamiento horizontal. En móvil, “Menú de administración” permite contraer el sidebar sin cambiar el comportamiento de otras pantallas.

## Pruebas y verificaciones

Resultado final: **175 pruebas, 1.535 aserciones, cero fallos y cero errores**, aproximadamente 57 segundos. Incluye **25 pruebas nuevas, 301 aserciones**, y regresión de:

- permisos administrativos generales y académicos;
- acceso a cursos y estados de curso;
- reporte existente de asistencias y panel de mensualidades;
- validación de notas del docente, servicio de boletas y portal Mi curso del alumno.

Cobertura nueva: administrador, invitado, otros roles, métodos de escritura rechazados, tipos inexistentes, vacíos, filtros combinados, curso histórico, alumnos únicos, rechazo de duplicados por el esquema, sobrecupo, promedio con cero y promedio almacenado sin recalcular, opcionales de otro curso, fechas inclusivas, pagos pendientes/realizados, formatos de mes, asignaciones activas ambiguas, paginación, resumen global, HTML escapado, ausencia de datos personales innecesarios y ejecución exclusiva de SELECT/PRAGMA. Se verificó además que aumentar los maestros de una página no aumenta el número de consultas.

Comando ejecutado desde la raíz del proyecto:

```powershell
& 'C:\wamp64\bin\php\php8.4.15\php.exe' -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'ReportesAdministrativosTest|AdminAsistenciasReporteTest|MensualidadesPanelTest|AdminGlobalRoleAccessTest|AdminAcademicRoleAccessTest|AdminCursosAccesoTest|DocenteNotasValidacionTest|CursoEstadoTest|BoletaServiceTest|MiCursoAlumnoTest' --do-not-cache-result
```

El bootstrap existente fuerza SQLite `:memory:`, sesión/cache en memoria y almacenamiento temporal aislado; el nuevo test comprueba también el PDO efectivo antes de migrar. Las pruebas no escribieron en MySQL ni en el almacenamiento documental real.

Otras comprobaciones:

- Sintaxis PHP correcta en controlador, servicio y pruebas.
- Las siete pantallas (portada y seis detalles) renderizan Blade sin errores.
- `route:list --path=admin/reportes --json` confirma ambas rutas con `web`, `auth`, `role:admin`.
- Compilación Vite correcta, sin instalar dependencias. Se realizó en una salida temporal y se publicó únicamente el nuevo CSS principal y su referencia en el manifest. El CSS responde HTTP 200 en `itm.test`.

## Comprobación con datos reales, exclusivamente de lectura

Se utilizó un verificador temporal de consola que confirmó conexión MySQL 8.4.7, base `itm`, puerto 3306; abrió `SET TRANSACTION READ ONLY`, ejecutó consultas y renderizó las vistas con un usuario administrador **solo en memoria**, y finalizó con rollback. No creó usuarios, pagos, sesiones de base de datos ni registros académicos.

| Reporte | Resultado observado |
| --- | --- |
| Alumnos | 6 alumnos únicos con inscripción aprobada |
| Cursos | 3 |
| Calificaciones | 4 registros |
| Asistencias | 4: 3 presentes, 1 ausente, 0 permisos |
| Mensualidades | 5: $75.00 pendientes y $50.00 marcados pagados |
| Maestros | 1 |

Los conteos generales y montos se contrastaron también con SELECT independientes. Los hashes de las filas de las 29 tablas, con 129 filas en total durante la comprobación, coincidieron antes y después. Los 109 PDF conservaron contenido y rutas: 5.132.534 bytes y huella agregada SHA-256 `959568fb93aee426003a20bcb30fce7234cb62f6521bfbbd4e28c1ab4773e5c5` (mapa ordenado de rutas relativas y SHA-256 individuales).

`.env` conserva SHA-256 `A97AEC4EDFD553A0C8F99D3C7482B1C35F6D48AA991556FA982AB89F549DA8FD`. Se verificaron `enabled`, `legacy_read_enabled`, `history_enabled` y `transition_guard_enabled`: los cuatro continúan en `false`. No se modificaron Apache, ACL, modelos/reglas existentes ni componentes/configuración de seguridad documental. No se activó ni ejecutó la transición. No se realizaron operaciones Git.

## Limitaciones y demostración

Los seis reportes pudieron implementarse con relaciones reales; no quedó un reporte bloqueado. No se añadió exportación PDF/Excel ni un promedio general de boleta: las cifras de calificaciones corresponden a cada materia y al valor guardado. Las materias inactivas se excluyen de Calificaciones según la regla vigente; los registros de asistencia y obligaciones sí conservan su historial. No se corrigen inconsistencias de datos desde Reportes.

Los catálogos de cursos y materias se cargan para los selectores; las filas de resultados están paginadas. No se realizó una sesión gráfica autenticada de navegador en producción: la autorización se verificó mediante HTTP de prueba sobre SQLite y la presentación real mediante renderizado de consola en una transacción de lectura. La revisión visual interactiva en móvil queda para la demostración.

Pasos exactos para presentar al maestro:

1. Abrir `http://itm.test/login` e iniciar sesión con una cuenta administradora existente.
2. Pulsar **Reportes** en el menú lateral, o abrir `http://itm.test/admin/reportes`.
3. Abrir **Alumnos inscritos**: mostrar el indicador de 6 alumnos únicos y seleccionar un curso; explicar que los cursos aprobados históricos se agrupan sin repetir al alumno.
4. Abrir **Cursos**: mostrar estado académico, visibilidad, cupos 20, inscripciones aprobadas y disponibilidad. Filtrar por estado y luego pulsar Limpiar.
5. Abrir **Calificaciones**: seleccionar curso y materia; mostrar laboratorio, examen, práctica, conducta y promedio almacenado. No hace falta capturar ni modificar notas.
6. Abrir **Asistencias**: sin filtros se observaron 4 registros. Elegir un período usando las fechas visibles; mostrar presentes, ausentes y permisos. Un período sin registros debe mostrar el estado vacío.
7. Abrir **Mensualidades**: mostrar $75.00 pendientes y $50.00 marcados pagados; alternar los estados, año y mes. Aclarar que solo se consulta y no se cobra.
8. Abrir **Maestros**: mostrar estado y asignaciones activas; probar el filtro por curso.
9. Reducir el ancho o usar un móvil: contraer **Menú de administración**, recorrer tarjetas y desplazar horizontalmente las tablas si es necesario.
10. Volver al Panel principal. Las cifras anteriores son una fotografía de la verificación; pueden cambiar por operaciones legítimas posteriores del sistema.

La implementación se detiene en este módulo, sin avanzar a fases documentales.

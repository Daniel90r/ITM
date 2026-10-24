# Autocompletado de filtros ITM

## Inventario y alcance

Se revisaron rutas, controladores, vistas y JavaScript. Solo se mejoran campos de búsqueda ya existentes; no se añaden filtros a formularios de inscripción, observaciones ni edición de expedientes.

| Pantalla | Campos mejorados | Dependencias y alcance |
|---|---|---|
| Alumnos | Alumno | Curso y año del listado; no exige aprobación para existir como expediente. Para filtros académicos se toma la primera inscripción aprobada, como en su listado. |
| Maestros | Maestro | Nombre, apellido y código real del expediente. Incluye activos e inactivos como el listado original. |
| Cursos | Curso | Turno seleccionado. Cursos no tiene columna de código institucional: se muestran nivel, sección y año, sin inventar códigos. |
| Materias | Materia | Nombre y código existentes. Conserva activas e inactivas. |
| Solicitudes | Alumno o código | Reutiliza ConsultaSolicitudesInscripcion y respeta el estado de la solicitud. |
| Asistencias administrativas | Alumno o código | Reutiliza consultaFiltrada: curso, materia, maestro y fechas del historial. |
| Notas administrativas | Curso; alumno | Cursos activos del listado; alumnos con notas dentro del curso buscado. |
| Boletas administrativas | Curso; alumno del curso | Cursos y alumnos con inscripción aprobada; el buscador de alumno exige el contexto de curso abierto. |
| Mensualidades | Alumno | Consulta original de inscripciones aprobadas, activas y de curso activo; año, curso, mes y estado existentes. No exige pagos previos. |
| Pagos realizados | Alumno o código | Conserva su endpoint y consulta de pagos reales por año/mes. |
| Estados de cuenta | Nombre o código | Inscripción aprobada o mensualidades históricas. Conserva retirados e inactivos con obligaciones. |
| Encargados | Alumno o encargado | Consulta original de alumnos menores de edad y coincidencias en sus encargados. La sugerencia identifica al alumno; no devuelve datos de contacto del encargado. |

Son 14 buscadores en 12 pantallas. No se precargan nuevos catálogos de personas en JavaScript. Los listados preexistentes que ya cargaban filas completas mantienen su arquitectura; el autocompletado solicita como máximo ocho resultados al servidor.

## Controles conservados

- Directores y listado de Alumnos inscritos: no tenían un buscador de texto equivalente. No se añade uno.
- Reportes institucionales: conservan selectores de curso, materia, estado, mes, año y modalidad, según cada informe; no se crean búsquedas de texto ni se cambian PDF.
- Docentes: los botones Buscar existentes trabajan con selectores de cursos/materias autorizados, sin buscador de texto. Se mantienen sus restricciones y controles.
- Alumnos: los campos de texto revisados pertenecen a formularios, no a búsquedas; no se convierten en autocompletados.
- Selectores académicos dependientes: no se sustituyen. Conservan valores, eventos y validaciones actuales. Añadirles un campo de búsqueda no es necesario para uniformar los buscadores de texto existentes.

## Corrección de envíos al escribir

Se reprodujo en Chrome un conflicto con los manejadores heredados `oninput="filtrarMensualidades()"` y `oninput="filtrarSolicitudes()"`. Ambos programaban `requestSubmit()` a los 400 ms, independientemente de las sugerencias AJAX a los 300 ms. La pila capturada apunta a los recursos compilados de Mensualidades y Solicitudes. No era un botón con tipo incorrecto ni un envío del endpoint de sugerencias.

El componente compartido retira los manejadores inline de input/change/keyup únicamente del campo de texto configurado con envío de consulta (`submit: true`). Escribir queda a cargo de AJAX; seleccionar una sugerencia envía el formulario existente una sola vez. Los controles adicionales, sus eventos y el botón manual se conservan. Los filtros locales (`submit: false`) mantienen sus manejadores. La selección ignora eventos repetidos cuando la lista ya está cerrada.

La comprobación visual original esperaba 350 ms y no detectaba los envíos a los 400 ms. La regresión de esta corrección espera 700 ms después de cada acción y comprueba explícitamente llamadas al envío, URL, foco y duplicados. Las pruebas JavaScript reproducen el manejador anterior y verifican que no se invoque al escribir ni al seleccionar. No se modificaron las consultas ni los JavaScript financieros de cobro.

Resultados de esta corrección (ejecuciones independientes): ocho pruebas JavaScript aprobadas; 45 pruebas PHP aprobadas con 668 aserciones en SQLite aislado; 70 comprobaciones Chrome para los 14 buscadores y cinco tamaños; cuatro comprobaciones adicionales de selección por ratón, botón manual y enlace Limpiar en Mensualidades/Pagos a 375 y 1440 px. Los envíos se interceptan en las vistas sintéticas para comprobar sus parámetros sin navegar hacia la aplicación real. Limpiar conserva su destino original; en Mensualidades mantiene el año seleccionado. Los archivos de esta corrección son el JavaScript compartido, sus pruebas JavaScript y esta documentación.

## Reutilización y seguridad

`components/filtros-autocompletado.blade.php` registra los adaptadores por nombre de ruta y selector existente. El layout los carga solo en administración. `filtros-autocompletado.js` y su CSS gestionan todas las interacciones: mínimo de dos caracteres, debounce de 300 ms, límite de ocho, flechas/Enter, clic, Escape, clic exterior, limpieza y cancelación/versión de respuestas.

La lista se coloca junto al campo en la capa de popovers del navegador cuando está disponible; tiene alternativa posicionada y límites al viewport. Esto evita recortes por tarjetas, tablas y contenedores con overflow. Los textos se insertan con textContent. Se utilizan combobox/listbox/option, aria-expanded, aria-activedescendant y una región de estado accesible.

Los endpoints son GET dentro de auth y role:admin. Solo devuelven identificador, nombre y código o descripción académica. Se envía Cache-Control privado sin almacenamiento. No se aceptan nombres de tablas/columnas del cliente; el controlador genérico utiliza una lista cerrada de módulos y parámetros validados. No se añaden endpoints docentes o de alumnos que amplíen sus ámbitos de acceso.

Mensualidades, Asistencias, Encargados y Estados de cuenta utilizan sus mismos constructores de consulta antes de paginar. `SugerenciasFiltros` limita y presenta personas sin cargar relaciones innecesarias. Solicitudes reutiliza su servicio; Pagos conserva su endpoint. El componente no altera cálculos, cupos, obligaciones ni registros.

Al seleccionar en consultas GET, se completa el código (o nombre si no hay código) y se envía el formulario existente conservando sus demás controles. Pagos mantiene además alumno_id; editar el campo elimina esa selección. En listados con filtros locales se completa el nombre y se ejecutan los eventos existentes. Los enlaces de paginación y sus parámetros continúan a cargo de cada módulo.

En Materias se encontró una llamada a filtrarMaterias sin definición en su módulo JavaScript. Se añadió únicamente el filtrado visual correspondiente y su exportación; no se alteró el cambio de estado ni el guardado.

## Verificación aislada

- PHPUnit: bootstrap que comprueba SQLite :memory: antes de inicializar el esquema; almacenamiento temporal, sesiones/cache en memoria y registro fuera del proyecto. No se utiliza MySQL real ni TransicionDocumentalTest.
- AutocompletadoFiltrosTest: alcance por módulo, estados, cursos/materias, retirados, historial económico, límite, códigos, comodines literales, datos mínimos, consultas de lectura y roles.
- OrganizacionFinancieraTest y regresión de mensualidades/historial: conservan año, mes, alumno y búsqueda en la paginación y los indicadores.
- `node tests/js/autocompletado-filtros.test.mjs`: cinco pruebas de interacción, debounce, teclado, clic, limpieza, filtros, respuestas antiguas y resultados vacíos.
- Chrome: 70 casos (14 campos × 320/375/390/768/1440 px), con vistas Laravel renderizadas en SQLite desechable, recursos reales compilados y respuestas AJAX sintéticas. Se verifica geometría, visibilidad por encima del contenido, selección por teclado, ausencia de coincidencias y cierre. Las capturas y el servidor estático de prueba se mantienen en la carpeta temporal de Windows, fuera del repositorio.
- Compilación Vite sin instalar dependencias. public/build es generado e ignorado por Git; el código compartido se incluye siguiendo el patrón de recursos inline existente en el proyecto.

Las comprobaciones visuales son sintéticas y no equivalen a una sesión con datos personales de producción. La compatibilidad SQL se prueba aquí con SQLite; no se ejecutan estas pruebas contra MySQL real. No se introducen índices ni cambios de esquema; el rendimiento de búsquedas parciales en volúmenes muy grandes requerirá medición específica antes de plantear índices.

## Archivos de esta implementación

Resultado de la ejecución conjunta de la implementación inicial: **112 pruebas aprobadas, 1605 aserciones, cero fallos/errores**. Incluye autocompletado, organización financiera, mensualidades, historial, responsividad, solicitudes, encargados, asistencias, integridad de boletas y Gestión de Maestros. JavaScript inicial: **5 pruebas aprobadas** en una ejecución independiente. Chrome inicial: **70 comprobaciones aprobadas**, con la limitación temporal explicada en la corrección anterior. Estas cifras corresponden a ejecuciones distintas y no se suman entre sí.

Nuevos:

- `app/Http/Controllers/Admin/SugerenciasFiltroController.php`
- `app/Services/SugerenciasFiltros.php`
- `resources/views/components/filtros-autocompletado.blade.php`
- `resources/js/filtros-autocompletado.js`
- `resources/css/filtros-autocompletado.css`
- `tests/Feature/AutocompletadoFiltrosTest.php`
- `tests/js/autocompletado-filtros.test.mjs`
- `docs/autocompletado-filtros.md`

Modificados:

- `app/Http/Controllers/Admin/AsistenciaController.php`: salida de sugerencias antes del resumen/paginación, usando su consulta existente.
- `app/Http/Controllers/Admin/MensualidadController.php`: salida limitada desde el mismo constructor del listado; se difiere la carga del catálogo para evitarla en sugerencias.
- `app/Http/Controllers/Admin/HistorialMensualidadController.php`: salida de sugerencias con la elegibilidad histórica existente.
- `app/Http/Controllers/Admin/EncargadoController.php`: salida de sugerencias con su consulta existente.
- `routes/admin.php`: cinco rutas GET internas protegidas; no se reemplazan las rutas existentes.
- `resources/views/layouts/app.blade.php`: registro de la configuración compartida para administración.
- `resources/views/admin/pagos.blade.php`: usa la interacción compartida en lugar del script y lista particulares.
- `resources/views/admin/boletas.blade.php`: expone al componente únicamente el identificador del curso abierto y lo limpia al volver.
- `resources/js/admin/materias.js`: definición del filtro visual que faltaba.
- `resources/js/admin/pagos-buscador.js`: retirado su código duplicado; la vista carga la implementación compartida.
- `resources/css/admin/pagos-movimientos.css`: retiradas las reglas particulares del desplegable; los botones y el campo conservan su diseño.

También se regeneró `public/build` mediante Vite, sin instalar paquetes. Sus archivos están ignorados por Git. No se modificaron bases reales, migraciones, PDF, documentos históricos, permisos ni flujos de guardado/cobro.

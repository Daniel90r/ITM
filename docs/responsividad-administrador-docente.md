# Paneles de Administrador y Docente: adaptación responsiva

## Auditoría y alcance

Se revisaron los layouts Blade y sus componentes compartidos antes de implementar.

| Grupo de pantallas | Problema encontrado | Adaptación compartida |
| --- | --- | --- |
| Panel principal de administrador y docente | Layout de 100vh, barra lateral de 220px y tarjetas con anchos mínimos | Área de contenido flexible, tarjetas en una o dos columnas y navegación móvil |
| Alumnos, inscritos, encargados, maestros, directores, cursos y materias | Filtros, formularios y encabezados con columnas o anchos fijos | Filtros en columna en teléfonos, controles contenidos y encabezados que permiten salto de línea |
| Solicitudes e inscripciones | Tablas amplias, acciones y formularios en modales | Desplazamiento dentro de la tabla, modales acotados al viewport y campos en una columna |
| Asistencias, notas y boletas | Tablas extensas y controles de registro anchos | Región de tabla accesible por teclado; se conserva el formulario y sus datos |
| Mensualidades, pagos e historial | Filtros, indicadores y barra de selección múltiple | Distribución adaptable y barra de acciones que admite varias líneas |
| Reportes y sus seis páginas | Menú mediante details distinto al resto del sistema | Mismo menú móvil, acciones y descargas existentes conservadas |
| Cursos/materias, alumnos, asistencia y notas del docente | Menú horizontal en móviles y tablas con campos editables | Menú desplegable, filtros compactos y tabla desplazable sin desbordar la página |

El panel de alumnos, el perfil genérico, las plantillas PDF y el reporte imprimible del docente quedan fuera. No se cambian rutas, permisos, consultas, validaciones ni cálculos.

## Implementación

- `layouts/app.blade.php` incorpora CSS y JavaScript únicamente para las rutas admin/docente de esos roles, excluyendo la impresión. Se utilizan los archivos fuente directamente para evitar recompilar recursos compartidos con alumnos.
- `components/staff-mobile-bar.blade.php` y `staff-menu-close.blade.php` proporcionan marca, hamburguesa, fondo y cierre explícito.
- Se identifican las barras existentes en `admin-sidebar`, `docente-sidebar` y el dashboard docente, conservando sus enlaces y formularios.
- `resources/js/staff/responsive.js` adapta el contenedor existente sin reconstruir formularios ni cambiar valores. Identifica grids, filas, tablas y modales, incluyendo contenido añadido posteriormente. No realiza peticiones HTTP.
- `resources/css/staff/responsive.css` aplica los ajustes solo a estos paneles y en medios de pantalla. Hasta 1023px se utiliza el menú superpuesto; desde 1024px continúa el lateral tradicional. A 520px o menos los filtros y formularios pasan a una columna.
- El menú se cierra con Escape, fondo, botón o selección de enlace. El contenido queda inerte mientras está abierto; el foco se contiene en la navegación y vuelve al botón al cerrar. La barra oculta no recibe foco.
- Se neutralizan en este ámbito las reglas antiguas que convertían el lateral en menú horizontal. Su prioridad de apilamiento no puede colocar el fondo por encima de la navegación. El cierre permanece visible al desplazar el menú.
- Las tablas conservan columnas y nombres; su región permite desplazamiento y foco de teclado. Los modales mantienen desplazamiento vertical y acceso a las acciones inferiores.

## Pruebas y reproducción segura

Los datos de prueba son ficticios: 31 alumnos, curso, materia, asignación docente, inscripción y una obligación mensual. Se renderizan 30 pantallas con SQLite en memoria. No se consulta la aplicación activa para las capturas.

```powershell
$env:ITM_STAFF_PREVIEW_DIR = 'C:/wamp64/www/ITM/storage/app/reportes-referencia/responsive'
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter StaffResponsiveTest
node tests/Frontend/staff-responsive.cjs
node tests/Frontend/asistencias-reportes.test.cjs
```

Crear previamente el directorio privado de vistas de prueba si no existe. El bootstrap exige SQLite en memoria y redirige almacenamiento, sesiones, vistas compiladas y documentos a un directorio temporal. El navegador usa un perfil exclusivo y bloquea HTTP/HTTPS; solo abre esos HTML locales. El script cierra únicamente su propia instancia de Chrome. Se necesita Chrome en la ruta configurada en el script y permiso para iniciar un navegador de prueba. Chrome elige un puerto libre y lo comunica mediante su perfil exclusivo, evitando conectarse a navegadores ajenos.

La comprobación de navegador cubre 320, 375, 390, 768 y 1440px: inicialización, ancho de página, tablas contenidas, controles visibles, modales, escritorio, apertura/cierre, foco y alcance real del botón de cierre. Las capturas y resultados quedan en `storage/app/reportes-referencia/responsive`, fuera del disco público.

La regresión PHP seleccionada completó **321 pruebas, 321 aprobadas, 8481 aserciones**, en una sola ejecución. Incluye autorización administrativa, navegación docente, notas/asistencias, mensualidades e historial, reportes/PDF, boletas, solicitudes y regresión del portal de alumnos. No es la suite completa del proyecto.

La comprobación específica final de `StaffResponsiveTest` completó **2 pruebas y 200 aserciones**, incluyendo etiquetas accesibles en español. Esta ejecución se informa separadamente, sin sumarla a la regresión anterior. Las **3 pruebas JavaScript** existentes de filtros y descarga de Asistencias también pasaron.

La pasada final de navegador completó **150 comprobaciones (30 pantallas × 5 anchos), sin fallos**. Se revisaron visualmente capturas de administrador, menú abierto, modal de alumno, historial, reportes, notas y asistencia docente, incluyendo escritorio. Se comprobó también que el botón de cierre recibe interacción real sin quedar cubierto por el fondo.

Se corrigió una aserción de `BoletaEmissionControllerTest` para comprobar el atributo del botón de emitir, en lugar de buscar la palabra disabled en todo el HTML/CSS/JavaScript. El renderizado de prueba de la vista heredada de notas se ejecuta en un proceso independiente porque esa vista declara una función PHP global; no se alteró esa lógica.

## Límites

- Verificación en Chrome de escritorio con viewports emulados; falta validación en dispositivos físicos, Safari/iOS y con teclado virtual abierto.
- Las tablas anchas requieren desplazamiento dentro de su contenedor para conservar todos los campos y controles.
- No se enviaron formularios contra el sistema real ni se modificaron datos, documentos, PDF, configuración Apache o procesos PHP. No se hicieron commits ni push.
- La adaptación compartida depende de JavaScript; si se desactiva se conserva la presentación previa del sistema.

## Archivos de esta implementación

```text
resources/views/layouts/app.blade.php
resources/views/components/admin-sidebar.blade.php
resources/views/components/docente-sidebar.blade.php
resources/views/components/staff-mobile-bar.blade.php
resources/views/components/staff-menu-close.blade.php
resources/views/docente/dashboard.blade.php
resources/css/staff/responsive.css
resources/js/staff/responsive.js
tests/Feature/StaffResponsiveTest.php
tests/Feature/BoletaEmissionControllerTest.php
tests/Frontend/staff-responsive.cjs
docs/responsividad-administrador-docente.md
```

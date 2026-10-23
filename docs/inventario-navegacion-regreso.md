# Inventario de controles de regreso

Se revisaron las vistas Blade administrativas, docentes y de alumnos, buscando textos de regreso, flechas, enlaces sin texto, destinos anteriores, cancelaciones y acciones JavaScript. No se revisan ni modifican las plantillas PDF como navegación de pantalla.

| Vista | Cantidad | Destino o acción existente | Decisión |
|---|---:|---|---|
| `admin/reportes/{alumnos,cursos,calificaciones,asistencias,mensualidades,maestros}` | 6 | `admin.reportes` | Componente de regreso |
| `admin/reportes/index` | 1 | `admin.dashboard` | Componente, texto «Volver al panel principal» |
| `admin/curso-detalle` | 1 | `admin.cursos` | Componente, texto «Volver a Cursos» |
| `admin/curso-editar`, encabezado | 1 | `admin.cursos` | Componente, sin tocar el formulario |
| `admin/maestro-detalle` | 1 | `admin.maestros` | Componente, texto «Volver a Maestros» |
| `admin/boleta-detalle` | 1 | `admin.boletas` | Componente con texto «Volver a Boletas» |
| `admin/boletas`, vista de alumnos | 1 | `volverACursos()` | Componente como botón de tipo `button`; conservar la acción local |

Total: **12 controles confirmados**. Los destinos existentes no llevaban parámetros; se conservan las expresiones de ruta. No se introduce `history.back()` ni se utiliza el encabezado Referer para navegar.

## Controles que se conservan sin cambios

- «Cancelar» de edición de curso: sí es un enlace a `admin.cursos`, pero pertenece a las acciones de un formulario; se conserva para evitar alterar el flujo de edición. El regreso del encabezado sí se unifica.
- Cancelaciones de modales de alumnos, maestros, materias, cursos, asignaciones, mensualidades, solicitudes y cierre de sesión: cierran o descartan interacciones, no son regresos de página.
- «Cancelar» de selección de pagos: limpia selección, no navega.
- «Anterior» de inscripciones y solicitudes y componentes de paginación: cambian de página dentro del mismo listado; conservan sus parámetros.
- Menús laterales y enlaces de panel principal de docentes y alumnos: navegación principal, no controles de regreso. No se añadieron botones nuevos donde no existían.
- Acciones de reapertura de solicitudes, confirmaciones y mensajes que contienen «volverá»: operaciones de negocio, no navegación.

## Componente

`components/boton-regreso.blade.php` utiliza un enlace cuando recibe `href` y un botón `type="button"` para la acción local confirmada. Comparte fondo claro, borde visible, texto oscuro, flecha decorativa, foco visible y tamaño mínimo de 44 px. Sus estilos se incluyen una sola vez mediante la pila `styles` de `x-app-layout`, sin depender de una recompilación de recursos.

Las rutas siguen protegidas por sus permisos existentes. No se modifican controladores, rutas, formularios, JavaScript de guardado, base de datos ni documentos.

## Validación

Una ejecución aislada con `tests/documentos-isolated-bootstrap.php` finalizó con **146 pruebas PHP aprobadas y 863 aserciones**, sin fallos ni omisiones. Se seleccionaron `ReportesAdministrativosTest`, `AdminCursosAccesoTest`, `AdminAcademicRoleAccessTest`, `ModalAsignarCursoMaestroIdTest`, `BoletaIntegridadTest`, `BoletaEmissionControllerTest`, `DocenteDashboardViewTest`, `AlumnoPortalAccesoTest` y `PortalAlumnoResponsiveTest`. El bootstrap fuerza SQLite en memoria y almacenamiento temporal para proteger los datos y documentos reales.

Por separado pasaron las **3 pruebas JavaScript** de navegación dependiente e invalidación de descarga de Asistencias. `git diff --check` no encontró errores de espacios. El inventario posterior confirmó los 12 componentes y sus destinos originales. Queda pendiente la comprobación visual en navegadores y dispositivos reales; el comportamiento HTTP y los permisos se verificaron automáticamente.

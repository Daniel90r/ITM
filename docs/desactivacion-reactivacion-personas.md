# Desactivación y reactivación de expedientes

## Alcance implementado

- Gestión administrativa desde **Estado y acceso** en alumnos y maestros.
- `alumnos.activo` y `maestros.activo` siguen siendo la fuente de verdad.
- Suspender no cambia matrícula, cupos, notas, conducta, asistencias, mensualidades, pagos, boletas ni documentos.
- Reactivar permite una nueva autenticación; no reabre matrículas ni asignaciones.
- Un docente no se puede suspender con asignaciones `activa` o `programada`, aunque el curso esté oculto.
- Las asignaciones programadas se pueden finalizar explícitamente. Se conservan fechas y motivo anterior; el nuevo motivo, responsable y fecha administrativa se registran en una tabla separada.
- Los motivos de ambas auditorías están disponibles únicamente en rutas administrativas.
- Expedientes sin cuenta pueden gestionarse con advertencia. Asociaciones de cuenta incorrectas o ambiguas impiden la transición.

## Seguridad de sesiones

El acceso se valida antes de completar el login y en el grupo web. Las sesiones de alumnos/docentes contienen `auth_version`; una sesión sin versión o con una versión anterior es rechazada. Los administradores no están sujetos a este nuevo requisito.

La desactivación y reactivación incrementan la versión y rotan `remember_token`. Solo las cookies persistentes emitidas por el nuevo flujo, marcadas `itm-access-v1:`, pueden establecer una nueva sesión. Una cookie previa al despliegue exige login con contraseña. No se cambian contraseñas.

Las solicitudes autenticadas admitidas de alumnos/docentes mantienen el bloqueo transaccional de su cuenta hasta terminar el controlador. La operación administrativa bloquea esa misma cuenta antes del expediente. Una petición completada antes de la suspensión conserva sus efectos; no se promete cancelar retroactivamente una descarga ya iniciada.

La asignación normal bloquea al docente y comprueba que siga activo. La suspensión relee con bloqueo todas sus asignaciones activas/programadas. No se cambió el scope académico `activas()` ni la lógica de matrícula.

## Correcciones de la auditoría final

Cada confirmación lleva una revisión persistente: el ID del último evento de auditoría de ese expediente, o 0 cuando todavía no existe ninguno. El servicio relee ese ID con bloqueo después de bloquear cuenta y expediente y antes de aceptar incluso un envío duplicado. Una revisión antigua se rechaza aunque `activo` haya vuelto al valor original. Este criterio funciona sin cuenta y no depende de que otras personas generen eventos. No se agregó un segundo contador: `users.auth_version` sigue destinado a las sesiones. La auditoría no se elimina ni reescribe por las operaciones del sistema.

Al finalizar una programada, la referencia del detalle se conserva únicamente si el mismo docente mantiene otra asignación activa o programada. Las programadas de otros docentes se conservan, pero no mantienen una referencia al docente que terminó. El detalle nunca se reasigna automáticamente al otro docente. Se preservan las fechas y el motivo anterior, y el cierre administrativo conserva su propia auditoría.

La prueba de sesión abierta establece primero `actingAs()` y después la versión antigua mediante `withSession()`, para que el helper no sobrescriba el escenario.

## Migración aditiva

`2026_10_24_000001_add_auth_version_and_persona_audit.php` agrega:

- `users.auth_version`, valor inicial 1.
- `cambios_estado_personas`, con persona, estados, motivo, administrador y fecha.
- `cierres_asignaciones_programadas`, con asignación, motivo, administrador y fecha.

Los triggers `cep_validate_insert` y `cep_validate_update` exigen exactamente un alumno o maestro por evento, estados booleanos y una transición entre estados distintos. SQLite utiliza `RAISE(ABORT)`; MySQL/MariaDB utiliza `SIGNAL SQLSTATE '45000'`. Se eligieron triggers para no depender de que una versión del servidor aplique restricciones CHECK. En MySQL se debe verificar previamente el privilegio TRIGGER y la ejecución real de estas sentencias en un entorno desechable.

### Recuperación de una ejecución DDL parcial

No se confía en una transacción global para DDL de MySQL. El procedimiento de una futura intervención autorizada es:

1. Mantener deshabilitadas las acciones de cambio de estado y cierre programado mientras el esquema esté incompleto. Conservar el bloqueo de acceso a personas suspendidas.
2. Antes de intervenir, obtener el respaldo e inventario autorizados de esquema, eventos y versiones. Esta implementación no los genera sobre datos reales.
3. Inspeccionar el registro de migraciones y los objetos existentes. No marcar manualmente la migración como completada ni reiniciar `auth_version`.
4. Reintentar únicamente esta migración. `up()` conserva una columna existente compatible, omite tablas ya creadas y completa índices y triggers faltantes. No borra ni trunca tablas ni vuelve a numerar eventos.
5. Si hay columnas incompatibles, claves foráneas faltantes/incompatibles, auditorías inválidas o duplicados que impidan una restricción única, la migración se detiene. Resolver la discrepancia mediante una intervención aditiva revisada antes de reintentar; no borrar registros para forzar el avance.
6. Verificar las columnas, relaciones, índices, ambos triggers, conteos y versiones frente al inventario. Solo habilitar las acciones cuando la migración figure completada y las pruebas de acceso e integridad pasen.
7. Si debe revertirse la funcionalidad, retirar las acciones nuevas manteniendo esquema, auditoría y revocación. No ejecutar `migrate:fresh`, `refresh` o un rollback destructivo sobre una base real.

Las pruebas simulan una interrupción después de crear la tabla de auditoría pero antes de sus índices, conservan un evento y una versión preexistente y comprueban que dos reintentos completan el esquema sin cambiarlos. Esto demuestra la recuperación en SQLite, no la semántica DDL de MySQL.

Los identificadores referenciados son `INT UNSIGNED`, siguiendo las migraciones finales declaradas del proyecto. Antes de un despliegue autorizado se deben comprobar sus tipos efectivos.

**Esta migración no se ejecutó en la base real.** El código nuevo requiere el esquema aditivo antes de habilitar su uso. Si falta la versión en la cuenta, el acceso de alumno/docente se rechaza; las nuevas pantallas administrativas también requieren las tablas correspondientes.

El método `down()` rechaza una reversión automática para evitar borrar auditorías o retirar la revocación. Una reversión operativa debe conservar las columnas y tablas, retirar las acciones nuevas si fuera necesario y mantener la protección de personas suspendidas. No restaurar una versión que permita reutilizar sesiones antiguas.

## Pruebas aisladas

Bootstrap: `tests/estado-personas-bootstrap.php`.

Antes de migrar, delega en el bootstrap existente que comprueba SQLite `:memory:` y su PDO efectivo. El almacenamiento y las cachés de paquetes/servicios se ubican en directorios temporales; correo, cola, caché y sesiones usan controladores de prueba. Se desactiva Xdebug en los comandos y se usa `memory_limit=1024M`.

Ejemplo para las pruebas propias:

```powershell
php -d memory_limit=1024M -d xdebug.mode=off vendor/phpunit/phpunit/phpunit --bootstrap tests/estado-personas-bootstrap.php --do-not-cache-result tests/Feature/EstadoPersonaTest.php tests/Feature/EstadoPersonaCaracterizacionTest.php
```

`Tests\TestCase::actingAs()` representa una autenticación reciente y carga su versión. Las pruebas de cookies anteriores sobrescriben o eliminan expresamente ese marcador; no se añade ninguna excepción de pruebas al código productivo.

La selección de regresión incluye autenticación, roles, portal, asignaciones, matrícula/cupos, documentos, boletas, historial mensual y reportes administrativos. No incluye `TransicionDocumentalTest.php`.

Resultado anterior a estas correcciones: **221 pruebas aprobadas, 1.426 aserciones, sin fallos**. Es una selección de regresión, no la suite completa. Las nuevas pruebas amplían esa misma selección; los resultados de ejecuciones distintas no deben sumarse.

Resultado conjunto final después de las correcciones: **236 pruebas aprobadas, 1.545 aserciones, cero fallos y errores**, en los mismos 17 archivos de regresión. Incluye 15 casos nuevos (contando variantes de datos). Las 43 pruebas propias están incluidas en esas 236; no se suman por separado. No se ejecutó la suite completa ni `TransicionDocumentalTest.php`.

No se identificó un entorno MySQL desechable configurado en el proyecto ni Docker/Podman disponibles en PATH. No se usó el MySQL de WAMP ni se aplicó esta migración sobre datos reales. Continúa pendiente verificar allí DDL, privilegios de triggers y concurrencia InnoDB.

### Archivos modificados

- `app/Http/Middleware/EnsureAlumno.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Models/Alumno.php`
- `app/Models/Maestro.php`
- `app/Models/User.php`
- `bootstrap/app.php`
- `resources/views/admin/alumnos.blade.php`
- `resources/views/admin/maestros.blade.php`
- `routes/admin.php`
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/TestCase.php`

### Archivos nuevos

- `app/Http/Controllers/Admin/EstadoPersonaController.php`
- `app/Http/Middleware/EstadoInstitucional.php`
- `app/Models/CambioEstadoPersona.php`
- `app/Services/AccesoInstitucional.php`
- `app/Services/CambiarEstadoPersona.php`
- `database/migrations/2026_10_24_000001_add_auth_version_and_persona_audit.php`
- `resources/views/admin/estado-persona.blade.php`
- `tests/Feature/EstadoPersonaCaracterizacionTest.php`
- `tests/Feature/EstadoPersonaTest.php`
- `tests/estado-personas-bootstrap.php`
- `docs/desactivacion-reactivacion-personas.md`

## Límites de verificación

Actualización: se completó posteriormente la validación aislada en MySQL 8.4.7, incluida concurrencia y recuperación DDL. Los resultados y las limitaciones actuales están en [validacion-mysql-personas.md](validacion-mysql-personas.md). Los puntos siguientes describen los límites de la ejecución SQLite original.

- SQLite no demuestra bloqueos de fila, interbloqueos ni compatibilidad DDL de MySQL/InnoDB. Falta una ejecución concurrente en MySQL desechable.
- No se consultaron datos reales ni se verificaron asociaciones de producción.
- No se desplegó, no se ejecutó la migración real y no se realizaron operaciones Git de publicación.
- La renderización Blade se prueba mediante HTTP de Laravel; no equivale a una revisión manual en navegadores y dispositivos reales.
- Esta funcionalidad no protege URL estáticas de documentos públicos ni revoca copias ya descargadas. La transición documental sigue siendo independiente.

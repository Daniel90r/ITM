# Validación MySQL/InnoDB de estado institucional

## Entorno y aislamiento

Validación local del 23 de octubre de 2026 en MySQL **8.4.7**, WAMP, `127.0.0.1:3306`, con InnoDB disponible. No había Docker/Podman accesibles en PATH.

Se enumeraron las bases antes de crear recursos. No se reutilizó `itm_concurrencia_test` ni ninguna base preexistente. Se crearon y conservaron:

- `itm_estado_test_20261023_97336a0eb8`: regresión y datos finales de concurrencia.
- `itm_estado_test_20261023_97336a0eb8_ddl`: recuperación DDL independiente.

Cuenta: `itm_test_97336a0eb8@localhost`. Tiene USAGE global y privilegios únicamente sobre esos dos nombres literales de esquema; se escaparon los guiones bajos en las concesiones cuando correspondía. No tiene privilegios de aplicación sobre `itm`.

La conexión administrativa inicial no seleccionó una base. Solo creó bases nuevas, cuenta y concesiones. Las conexiones Laravel posteriores comprobaron driver, base efectiva, puerto, UUID de servidor, identidad y procedencia antes de migrar o escribir. No se consultaron datos personales ni se escribió en `itm` u otras bases preexistentes de aplicaciones.

El manifiesto con credenciales está exclusivamente en el directorio temporal del usuario: `itm-mysql-97336a0eb8.json`. No se publica ni se agrega al repositorio. El almacenamiento de pruebas está en `itm-mysql-storage-97336a0eb8` dentro del mismo directorio temporal. No se modificaron `.env` ni `phpunit.xml`.

## Migraciones e integridad

Las migraciones históricas construyeron el esquema vacío sin modificar sus archivos. La migración aditiva creó `users.auth_version`, ambas tablas de auditoría y sus triggers. Se comprobaron **31 tablas InnoDB**, claves `INT UNSIGNED`, índices, restricciones únicas y claves foráneas restrictivas.

Los triggers MySQL `cep_validate_insert` y `cep_validate_update` rechazaron eventos sin persona, con ambas personas, estados iguales y estados fuera de rango. También se comprobaron rechazo de FK inválida, cierre duplicado y conservación de eventos válidos.

## Resultados independientes

No sumar ejecuciones diferentes como si fueran una suite:

| Ejecución final | Resultado |
|---|---|
| Regresión MySQL, selección de 236 casos | 234 aprobados, 2 omitidos, 1.722 aserciones; sin fallos ni errores |
| Regresión SQLite original | 236 aprobados, 1.545 aserciones; sin fallos ni errores |
| Arnés concurrente A–H | Todos los escenarios aprobados; 32 comprobaciones, incluida la prueba de bloqueo |
| Recuperación DDL e integridad MySQL | 20 comprobaciones aprobadas |

Las dos omisiones MySQL son explícitas: una prueba heredada que elimina la tabla de reactivaciones y otra que introduce fechas inválidas en DATE. MySQL estricto rechaza esas fechas; no se relajó el modo SQL. La recuperación DDL se prueba por separado en el segundo esquema.

Se adaptaron únicamente pruebas MySQL para sustituir precondiciones SQLite, reconocer SQLSTATE de unicidad y usar textos compatibles con VARCHAR(255). El primer intento de adaptación detectó esas diferencias; no fueron corregidas relajando restricciones ni cambiando código de negocio.

## Concurrencia real

Procesos PHP independientes, IDs de conexión diferentes y transacciones MySQL solapadas. Se retuvieron bloqueos desde la conexión coordinadora hasta que los participantes alcanzaron la barrera. Límite InnoDB: 8 segundos; límite por proceso: 25 segundos.

| Caso | Resultado final verificado |
|---|---|
| A. Dos administradores desactivan al alumno | Una transición y un evento; la otra revisión se rechaza. |
| B. Confirmación anterior al ciclo completo | Rechazada; el alumno permanece activo y no aparece un tercer evento. |
| C. Asignación normal frente a suspensión | No queda docente inactivo con asignación abierta. En la ejecución final ganó la suspensión y la asignación fue rechazada. |
| D. Programada frente a suspensión | Ganó la programación; suspensión rechazada y docente activo. El escritor sintético utiliza el protocolo transaccional de bloqueo del maestro, pues no existe un endpoint de creación de programadas. |
| E. Dos cierres de una programada | Un cierre y una auditoría; segundo cierre rechazado; fechas originales intactas. |
| F. Sustitución frente a suspensión | Probados receptor y saliente. No se asigna al receptor suspendido; la sustitución del saliente finaliza su historia y conserva autores. |
| G. Reactivación y sesión anterior | Perfil activo nuevamente, sesión anterior rechazada. |
| H. Fallo real al insertar auditoría | El trigger devuelve SQLSTATE 45000; se revierten estado, versión y token. |

Una prueba adicional mantuvo una fila bloqueada e intentó bloquearla desde otro PDO: recibió el error esperado **1205** tras aproximadamente **1.013 ms** (1,013 segundos). Se verificó así el bloqueo real y un límite finito, no solo códigos HTTP.

No hubo errores de interbloqueo propagados en los escenarios. El contador global `Innodb_deadlocks` no estuvo disponible; no se afirma ausencia absoluta de interbloqueos ni se hicieron cambios globales de configuración.

Evidencia externa: `concurrency/results.json` y `mysql-recovery-results.json` bajo el almacenamiento temporal identificado. Las bases y la cuenta de pruebas se conservaron para revisión.

## Recuperación DDL real

Se interrumpió la migración tras crear objetos y antes del primer índice de auditoría. Se conservó un evento y una cuenta con versión 7. Los reintentos completaron índices y triggers sin duplicarlos, borrar eventos ni reiniciar versiones.

También se cambió únicamente el valor predeterminado de la columna de la base desechable para volverlo incompatible: `up()` se detuvo de forma segura, conservando datos. Se restauró la definición correcta y se comprobó nuevamente el esquema.

## Reproducción

`tests/mysql-personas-create.php` prepara una ejecución nueva con nombres aleatorios, inventario de bases previas, cuenta limitada y manifiesto externo. No reutiliza bases. El script de provisión quedó preparado; en esta ejecución se usaron los mismos pasos mediante PHP administrativo controlado.

Para la ejecución ya conservada, establecer `ITM_MYSQL_MANIFEST` con la ruta externa. No ejecutar regresión nuevamente si se desean conservar sus fixtures finales: RefreshDatabase reconstruye exclusivamente la base propiedad de esta ejecución.

- `tests/mysql-personas-bootstrap.php`: aislamiento y verificación de conexión efectiva.
- `tests/Feature/MySqlEstadoPersonaTest.php` y `tests/MySql/`: adaptadores de la selección de regresión. Se omiten fuera del bootstrap MySQL autorizado.
- `tests/mysql-personas-concurrency.php`: escenarios A–H y diagnóstico de bloqueo; no borra bases.
- `tests/mysql-personas-recovery.php`: requiere que `ITM_MYSQL_DATABASE` sea el segundo esquema, inicialmente vacío; rechaza reutilizarlo si ya tiene migraciones.

No se ejecutó `TransicionDocumentalTest.php` ni toda la suite indiscriminadamente. Los 11 archivos excluidos permanecen fuera de esta validación.

## Dictamen

**APROBADO CON OBSERVACIONES para la validación local MySQL/InnoDB.** No se detectaron defectos adicionales de negocio en el alcance validado. Siguen pendientes el despliegue autorizado, la revisión del esquema real antes de migrarlo y las observaciones ya documentadas sobre URL públicas históricas y revisión manual de interfaz. Esta validación no es una autorización de despliegue.

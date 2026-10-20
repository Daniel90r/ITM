# ITM — Fase 2G-2B-1.9: evaluación económica y recuperación SQL aislada

19 de octubre de 2026, America/El_Salvador. Los timestamps UTC del respaldo
y del ensayo corresponden al 20 de octubre; no es una diferencia de fecha del
contenido, sino de zona horaria.

## Resultado ejecutivo

Se creó y verificó un respaldo SQL nuevo de **29 tablas y 128 registros** de
`itm`, MySQL **8.4.7**, y se restauró exclusivamente en otra instancia temporal
de la misma versión. Coinciden datos, columnas, opciones de tabla, índices y
**37 relaciones foráneas**, sin huérfanos. Las 29 comprobaciones `CHECK TABLE`
del destino devolvieron `OK`. Producción conserva sus datos y configuración.

La evaluación recomienda mantener el diseño de servicio externo con DB
transaccional y ancla independiente, **sin contratarlo todavía**. El tamaño
actual no justifica alta disponibilidad costosa por sí solo; primero se
necesitan responsables, presupuesto completo y un acuerdo explícito sobre
indisponibilidad segura. Una VM barata o una DB gestionada no sustituyen el
ancla ni la separación administrativa.

**No se desplegó el testigo, no se habilitó el canal real y no se ejecutó la
transición. No se modificó ni copió ningún PDF original.**

## 1. Antecedentes inspeccionados

Se leyó el diseño de fase 1.8 y se revisaron los artefactos privados de 2D/2F,
sus `verification.json`, `SHA256.json`, inventarios y la auditoría posterior
de fase 1.5. Ambos SQL anteriores contienen 28 tablas y sus verificaciones
registran `restore_test_executed=false`; se conservan intactos.

No se localizó un informe independiente de fase 2E en `docs` ni en los
directorios privados de respaldo disponibles. No se inventa su contenido.
La cobertura y el estado actuales se determinaron directamente de producción
y se verificaron con una restauración nueva, sin depender de esa ausencia.

Se inspeccionó `backup.bat` sin ejecutarlo: referencia un dump histórico y no
explicita `--single-transaction`, host/puerto ni prueba de restauración. No se
reutilizó como procedimiento de esta fase ni se modificó.

Git se consultó exclusivamente en lectura mediante `--no-optional-locks
status`. Se conservaron los numerosos cambios locales anteriores, incluidos
archivos modificados, eliminados y no seguidos. No se ejecutaron operaciones
Git de escritura ni se crearon worktrees.

## 2. Comparación técnica, económica y operativa

| Criterio | Testigo dedicado + PostgreSQL + ancla externa | Otro equipo con administración separada | Servicio gestionado transaccional + continuidad externa |
| --- | --- | --- | --- |
| Costo inicial | Desarrollo, PKI, ancla, pruebas, revisión y puesta en marcha; sin cotización completa | Equipo/UPS o reutilización validada, administración y desarrollo; sin cotización local | Integración API/IAM, firmador y ancla; sin cotización del desarrollo |
| Mensual | VM + nodo DB + respaldo + ancla + custodia + monitoreo + horas humanas | Energía, red, backup, repuesto y operador; no puede suponerse gratuito | Peticiones/almacenamiento/PITR + cómputo del servicio + claves + ancla + operación |
| Mantenimiento | Medio/alto: servicio y políticas; proveedor opera DB | Alto: SO, discos, DB, energía, certificados y recuperación | Menos operación de discos, más IAM, cuotas, API y dependencia de proveedor |
| Disponibilidad | Un nodo no es HA; cierre seguro al caer; HA requiere plan y costo específicos | Depende del equipo, sede, UPS y red; requiere repuesto/restore | Disponibilidad gestionada no elimina caída de red, identidad o ancla |
| Independencia | Cuenta/administración externa a ITM; ancla en otra frontera | Solo válida con otro host, administradores y respaldos | Separar cuenta y permisos del ancla; otra tabla en la cuenta controlada por ITM no basta |
| Consumo único | Transacción, UNIQUE y CAS de reserva independiente antes de ejecutar | Igual, con DB duradera y restricciones | Transacciones/condiciones de escritura; no aceptar éxito idempotente como consumo nuevo |
| Restauraciones | Comparar head/reservas con ancla; DB transaccional sola no protege | Snapshots/copias locales también requieren ancla | PITR recupera datos anteriores: sigue siendo necesaria continuidad fuera del rollback |
| Claves | Firmante, testigo, ejecutor y ancla separados | Custodia fuera de Wamp/LocalSystem; hardware/operación a cargo del operador | IAM/KMS no implican soporte Ed25519; confirmar custodia y recuperación por separado |
| Recuperación | Restore aislado + reconciliación completa con journal/ancla | Restaurar equipo/DB y reconciliar antes de responder libre | Restore/PITR a recurso nuevo, validar historia e identidad, no reiniciar consumos |
| Complejidad para ITM | Moderada si hay operador externo responsable; alta si ITM implementa también el ancla | Mayor carga local y necesidad de otra persona responsable | Menos DBA, mayor especialización cloud; no hay solución integral v2 verificada lista para contratar |

Como tercera opción concreta se evaluó **DynamoDB Standard con transacciones
y escrituras condicionales, servicio firmador y ancla independiente**. AWS
documenta transacciones y condiciones de escritura; esto no certifica que
el proveedor entregue el protocolo ITM ni una defensa completa contra rollback.
No se encontró ni verificó un producto contratado que reúna todas esas
garantías sin desarrollo adicional.
[Transacciones](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/transactions.html),
[condiciones](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Expressions.ConditionExpressions.html).

### Precios verificables y condiciones

Consulta documental: **19/10/2026, USD**, sin contratación, impuestos,
descuentos prometidos o recursos creados.

| Referencia oficial | Región y condiciones registradas | Precio observado |
| --- | --- | --- |
| DigitalOcean Basic regular, 1 GiB/1 vCPU | Catálogo global; no se seleccionó ni cotizó una región concreta. Cómputo de una VM, no arquitectura completa | USD 6/mes; plan 2 GiB: USD 12/mes |
| PostgreSQL Standard Basic regular, 1 GiB/1 vCPU | Catálogo global, un nodo, 0 nodos adicionales; región no discriminada en la tabla consultada | USD 15,15/mes; almacenamiento mostrado a USD 0,215/GiB/mes, incrementos de 10 GiB; confirmar inclusión del mínimo en cotización |
| Backup porcentual de VM DigitalOcean | Catálogo global, modalidad semanal o diaria elegida; no incluye ancla | 20% semanal / 30% diario del costo VM: para USD 6, cálculo ilustrativo USD 1,20 / 1,80 mensuales |
| DynamoDB Standard on-demand | Ejemplo oficial **US East (N. Virginia), us-east-1**; escritura estándar hasta 1 KiB, lectura fuerte hasta 4 KiB | USD 0,625 por millón de unidades de escritura; USD 0,125 por millón de unidades de lectura |
| Almacenamiento DynamoDB del ejemplo | Misma región y clase; no se presupone elegibilidad de ITM a free tier | Referencia USD 0,25/GB-mes; beneficios gratuitos sujetos a condiciones, excluidos de este presupuesto |

Fuentes de las líneas anteriores:
[VM y backups](https://www.digitalocean.com/pricing/droplets),
[nodos DB](https://www.digitalocean.com/pricing/managed-databases),
[DynamoDB](https://aws.amazon.com/dynamodb/pricing/).

La suma orientativa verificable **VM + nodo DB = USD 21,15/mes**. Con backup
semanal de esa VM: USD 22,35/mes; con diario: USD 22,95/mes. Son sumas de
líneas de catálogo, **no cotizaciones de producción** ni confirmación regional.
No incluyen almacenamiento DB según desglose, ancla/journal independientes,
PKI/custodia Ed25519, alertas, tráfico, desarrollo, soporte o personal. No se
obtuvo un precio verificable para esas partidas ni para equipo/energía local;
el **costo completo inicial y mensual sigue pendiente de cotización**.

En DynamoDB las operaciones transaccionales consumen más unidades que las
estándar; el costo depende además de cantidad/tamaño de ítems por transacción,
retos, auditoría y consultas. Las tarifas no se multiplican directamente por
el número de permisos. PITR, cómputo API y ancla se presupuestan aparte.
La [recuperación PITR](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Point-in-time-recovery.html)
no evita restauraciones antiguas por sí misma.

**Cambio de catálogo relevante frente a una extrapolación de 1.8:** la
documentación de DigitalOcean indica restricciones a nuevos clusters Standard
desde el 15/10/2026 para nuevas cuentas y desde el 30/11/2026 para todas:
sin planes de más de 4 GiB ni nuevos standby/read-only en esos clusters.
No se debe prometer HA agregando simplemente otro nodo Standard barato.
Advanced comienza en USD 130/mes por nodo según documentación, con standby
facturado al precio de su primario; requiere cotización regional actual.
[Precios y cambio de planes](https://docs.digitalocean.com/products/databases/postgresql/details/pricing/).

### Recomendación proporcionada a ITM

Los 8 expedientes de inscripción, 128 filas totales y 109 PDF no necesitan
dimensionamiento de gran volumen. Además, el testigo protege operaciones de
transición autorizadas; no se ha habilitado como dependencia de las funciones
web actuales. La prioridad es **separación y continuidad**, no capacidad.

Mantener como principal servicio dedicado con PostgreSQL gestionado y ancla
externa, inicialmente en laboratorio. Un único nodo solo sería aceptable
operativamente si se acuerda que una caída impide la transición hasta recuperar
y verificar continuidad; no se presenta como HA. Antes de contratar, cotizar
el conjunto y asignar operadores independientes. Si existe otro equipo y
administrador capaces de sostener restauración/monitoreo, evaluar esa alternativa
con costos reales de energía y horas; no asumir costo cero por reutilización.

No recomendar aún el desarrollo de una segunda plataforma cloud solo por
tarifas de peticiones pequeñas. El costo humano del ancla, claves y recuperación
puede dominar el gasto de infraestructura. Si esas responsabilidades no pueden
cubrirse, mantener el canal cerrado; no rebajar las garantías para ajustarlas
a un precio mínimo.

## 3. Auditoría previa y consistencia del respaldo

Identidad verificada por consulta: `DATABASE()=itm`, MySQL `8.4.7`, TCP
`127.0.0.1:3306`, UUID y datadir originales registrados privadamente. Se
comprobaron:

- **29 tablas base InnoDB**, 128 filas, 8 inscripciones, 6 referencias, cero
  documentos versionados, 37 FK y cero huérfanos.
- Sin vistas, triggers, rutinas o eventos en `itm`. `jobs` vacío; sin otros
  clientes activos o transacciones ajenas observadas al comprobar/exportar.
- Contadores DML/DDL globales y `COUNT_WRITE` de **las 29 tablas** iguales
  antes/después de exportación y en la auditoría final. Binlog desactivado:
  no se afirmó una verificación de posiciones binlog inexistentes.
- Aproximadamente 551 GB decimales libres en C: al revisar el destino, frente
  a un dump de 52.323 bytes y un datadir temporal de mayor tamaño. No se llenó el disco.
- Destino nuevo, sin reparse points en sus ancestros, fuera de Wamp/repositorio,
  DocumentRoot y Alias. `httpd -t -D DUMP_INCLUDES` confirmó los includes activos
  revisados, sin servir el directorio privado. No se editó ni recargó Apache.
- DACL protegida **solo en la carpeta nueva**, con usuario ejecutor, SYSTEM
  y Administradores. SQL, manifiesto y hashes heredan únicamente esas entradas.
  No se modificó la ACL del padre ni de archivos/directorios existentes.

Se utilizó `mysqldump.exe` 8.4.7 con:

```text
--single-transaction --quick --routines --triggers --events --hex-blob
--default-character-set=utf8mb4 --set-gtid-purged=OFF --no-tablespaces
--databases itm --result-file=<archivo privado nuevo> --skip-lock-tables
```

Conexión explícita TCP/127.0.0.1/3306; opciones de autenticación en archivo
privado temporal, nunca contraseña en CLI/repositorio. Se impidió cargar
login paths ajenos y no se ejecutó `backup.bat`. El archivo de credenciales de
exportación se retiró al terminar. `--result-file` evitó la recodificación
del SQL por redirección PowerShell.

La instantánea InnoDB se obtuvo con una sola transacción de mysqldump, sin
FLUSH, bloqueo global, `--source-data`, `--flush-logs` o LOCK TABLES sobre
producción. Se observó ausencia de escritores y se verificaron contadores,
DDL y hashes de filas sin cambios. No se confunde un exit code cero con
consistencia: si hubiese existido DDL, motor no transaccional o actividad no
controlada, el auxiliar no habría aceptado el archivo como válido. El manual
MySQL advierte que DDL concurrente puede invalidar `--single-transaction`.
[Procedimiento oficial](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html).

No se demuestra ausencia de acciones de un administrador hostil que pudiera
alterar simultáneamente evidencias y contadores. Es una copia lógica validada
durante una ventana observada sin cambios, no una instantánea distribuida de
SQL, PDF y todos los procesos del sistema. Los PDF se verificaron por separado
y no se copiaron en esta fase.

## 4. Respaldo privado retenido

Ruta, usando la variable del usuario para no incluir su perfil personal:

```text
%LOCALAPPDATA%\ITM-PrivateBackups\itm-fase-2g-2b-1-9-20261019-222859-259590dd\itm-full.sql
```

Archivos conservados en esa carpeta:

| Archivo | Tamaño | Función |
| --- | ---: | --- |
| itm-full.sql | **52.323 bytes** | Estructura y datos de las 29 tablas |
| manifest.json | 16.181 bytes | Inventario de hashes/conteos, método, restauración y límites |
| SHA256.json | 179 bytes | Huellas del SQL y del manifiesto; no contiene secretos |

**SHA-256 del SQL:**

```text
37caf9029766ab86e35ae96e49f90d22df08f5eabff0c5a808323d05a7adee99
```

SHA-256 del manifiesto:

```text
b68d8298415cb0218f905c42c73649b951f0c0ea4fb46b11decb87332b82ea21
```

Inicio de exportación: **20/10/2026 04:32:37 UTC**, equivalente a
**19/10/2026 22:32:37** local. Exportación y comprobaciones inmediatas:
aproximadamente **1,049 s**. Se verificaron tamaño no nulo, lista exacta de
29 `CREATE TABLE`, selección de `itm`, terminación del dump, salida sin errores,
SHA-256 y permisos. No se mostró su contenido SQL ni se copió al repositorio.

## 5. Instancia de restauración e aislamiento

Se usó el mismo binario MySQL **8.4.7** instalado, en un proceso independiente
y sin registrar servicio Windows. Se inicializó un datadir nuevo bajo
`<respaldo nuevo>\restore-work\data`; tmp, logs, PID, certificados y ficheros
de importación también quedaron dentro de `restore-work`.

Controles antes de escribir tablas:

- `--no-defaults`: sin cargar `my.ini` global o de producción.
- `--no-monitor`: proceso temporal propio identificado por PID; sin supervisor
  que pudiera reiniciarlo después del apagado.
- Bind exclusivo **127.0.0.1:43319**, distinto de los puertos existentes;
  dueño del listener comprobado contra el proceso iniciado.
- X Plugin, eventos, arranque de réplicas, binlog, named pipe, shared memory
  y LOCAL INFILE desactivados. Directorios de escritura explícitos y distintos
  del datadir original; sin plugins o archivos externos referidos por el dump.
- Contraseña temporal aleatoria en archivos privados. Usuario temporal con
  privilegios sobre `itm` de **esa instancia** y permiso de apagado; no se
  crearon cuentas en producción. La importación usó TLS con la CA temporal
  (`VERIFY_CA`), y host/puerto explícitos, sin reconexión automática.
- Se verificaron UUID diferente, datadir exacto, versión 8.4.7, scheduler OFF
  y ausencia inicial de tablas ITM. El cliente de importación apuntó únicamente
  a 43319; no se usó la conexión Laravel de producción como destino.

La separación es operativa y de configuración/conexión; el proceso comparte
el host y el binario. No se afirma que sea un sandbox contra un administrador
malicioso del host. El aislamiento comprobado impide que este procedimiento
utilice el datadir o conexión real como destino. No se cambiaron cuentas,
servicios, configuraciones globales o archivos de producción para conseguirlo.

## 6. Resultado de restauración y comparación

Inicio del ensayo final: **20/10/2026 04:42:08 UTC / 19/10 22:42:08 local**.
Importación: **4,903 s**, salida 0 y sin errores de importación. Comparación
y checks adicionales: **1,995 s**. No representan el RTO completo de una
emergencia: instalar dependencias, obtener autorizaciones y preparar el
destino requerirían tiempo adicional no medido como SLA.

Se compararon hashes SHA-256 deterministas de todas las filas (orden de lectura
independiente), definiciones de índices/PK, FK y sus reglas, todos los atributos
de columna —incluidos charset, collation, nulabilidad, default y extra—,
opciones de tabla y AUTO_INCREMENT. Las estimaciones estadísticas Cardinality
de índices no se trataron como estructura.

| Tabla | Producción inicial | Restaurada | Producción final |
| --- | ---: | ---: | ---: |
| alumno_encargado | 2 | 2 | 2 |
| alumno_materia | 1 | 1 | 1 |
| alumnos | 8 | 8 | 8 |
| asignaciones_docentes | 3 | 3 | 3 |
| asistencias | 4 | 4 | 4 |
| boletas | 0 | 0 | 0 |
| cache | 0 | 0 | 0 |
| cache_locks | 0 | 0 | 0 |
| curso_horarios | 1 | 1 | 1 |
| cursos | 3 | 3 | 3 |
| detalle_cursos | 6 | 6 | 6 |
| detalles_boletas | 0 | 0 | 0 |
| directores | 0 | 0 | 0 |
| documentos_inscripcion | 0 | 0 | 0 |
| encargados | 2 | 2 | 2 |
| failed_jobs | 0 | 0 | 0 |
| inscripciones | 8 | 8 | 8 |
| job_batches | 0 | 0 | 0 |
| jobs | 0 | 0 | 0 |
| maestros | 1 | 1 | 1 |
| materias | 3 | 3 | 3 |
| mensualidades | 5 | 5 | 5 |
| migrations | 59 | 59 | 59 |
| notas | 4 | 4 | 4 |
| pagos | 2 | 2 | 2 |
| password_reset_tokens | 0 | 0 | 0 |
| reactivaciones_inscripcion | 1 | 1 | 1 |
| sessions | 1 | 1 | 1 |
| users | 14 | 14 | 14 |
| **Total: 29 tablas** | **128** | **128** | **128** |

Las **37 FK** coinciden, con **cero huérfanos**. La estructura documental,
sus restricciones y sus cero filas coinciden. Las **29 tablas restauradas**
superaron `CHECK TABLE ... QUICK` con estado `OK`. Los hashes de datos son
iguales, no solo sus cantidades.

### Incidencias controladas durante el ensayo

1. El acceso inicial con root/localhost y resolución de nombres desactivada
   fue rechazado desde 127.0.0.1. Antes de importar datos se detuvo únicamente
   el PID temporal vacío, se preparó un usuario explícito loopback y se inició
   de nuevo solo esa instancia. Producción no se reinició.
2. La auditoría inicial detectó diferencias textuales de `SHOW CREATE` en
   **26 tablas**: el servidor restaurado explicita `CHARACTER SET utf8mb3` que
   el original presenta implícitamente. Igualar la sesión no eliminó esa
   representación. No se ignoró el fallo: se verificaron todos los metadatos
   de columnas, collations y opciones de tabla y se comparó DDL normalizando
   **solo esa cláusula redundante**. DDL canónico, índices, FK, charset y datos
   coinciden. No se afirma igualdad byte a byte de los DDL sin normalizar.
3. Se repitió la importación únicamente en la instancia desechable mientras
   se afinaba la validación. El archivo SQL conservó exactamente su SHA-256;
   no hubo correcciones sobre el dump, conversión del esquema o importación
   contra producción. Los auxiliares fueron retirados al concluir.

## 7. Integridad documental y respaldos anteriores

Los **109 PDF originales** conservan rutas y SHA-256 antes/después y coinciden
con los inventarios históricos. Permanecen las **6 referencias**, las 6
asociaciones verificadas y los **103 PDF sin asociación** en su ubicación
original. No se crearon versiones documentales reales ni se activaron opciones.

Se verificaron, sin copiar ni modificar archivos:

- Los **6 hashes registrados** en cada `SHA256.json` de 2D y 2F, incluidos sus
  SQL, inventarios y verificaciones: todos coinciden.
- Las **109 copias PDF de 2D**, con tamaños y SHA-256 coincidentes, total
  **5.132.534 bytes**.
- Los inventarios de ambas fases coinciden con los 109 originales actuales.
  2F no contiene otra carpeta de copias documentales; no se le atribuye una.

SQL histórico 2D:
`41e27552db082d6939c2678c471ac2efe16f3df0ceae5d67dcff90520dc15c49`.
SQL histórico 2F:
`8bff4769172dac6dab8d635b86738a07ba3655f194ee770ae9043236ff80ec98`.
No se modificaron sus manifiestos para atribuirles el ensayo del SQL nuevo.

**Este nuevo SQL no contiene PDF y no sustituye su respaldo documental.**

## 8. Recuperación que ahora puede demostrarse y límites

El SQL permite reconstruir la base `itm` al estado respaldado en MySQL
8.4.7, según el ensayo realizado. No demuestra una recuperación completa de
Windows, Wamp, Laravel, secretos, usuarios/grants MySQL, configuración global,
PDF o del futuro testigo. Esos componentes requieren respaldos/procedimientos
propios. No se ensayó importación en MariaDB, otra versión de MySQL o hardware
con configuración distinta; no se promete compatibilidad con ellos.

Verificación futura de una recuperación autorizada:

1. Comprobar archivo, SHA-256 y manifiesto por un canal de confianza; asegurar
   capacidad, permisos privados y dependencias de MySQL/PHP necesarias.
2. Preparar destino independiente y confirmar versión, UUID, host/puerto y
   datadir ajenos a producción antes de permitir importación.
3. Importar con credencial privada y sin `--force`, redirección de codificación
   o reconexión automática; revisar salida y errores, sin exponer SQL sensible.
4. Repetir comparación de las 29 tablas/128 filas, hashes, índices, columnas,
   collations, FK, referencias y comprobaciones de integridad.
5. Restaurar/verificar documentos por su mecanismo separado; comprobar su
   correspondencia con el estado SQL y sus hashes, sin inferir asociaciones.
6. Solo una autorización de recuperación real podría decidir una promoción;
   este informe no autoriza reemplazar la base actual.

Cambios posteriores a **22:32:37 local** no forman parte del SQL. Producción
tenía binlog desactivado: este artefacto no acredita recuperación PITR de
escrituras posteriores. No se habilitó binlog ni se cambió configuración.

El respaldo está protegido por ACL, **no cifrado**, y se conserva en el mismo
equipo: no protege por sí solo frente a pérdida del disco, robo con privilegios
administrativos o desastre de la sede. Copia cifrada/offsite, retención y
pruebas periódicas requieren autorización y presupuesto posteriores; no se
realizaron copias adicionales en esta fase.

La validación actual subsana el bloqueo concreto de SQL de 28 tablas sin
restauración probada. No transforma esta copia en autorización futura de
transición: habrá que verificar su vigencia frente a cambios posteriores y
completar el resto de requisitos.

## 9. Pruebas automatizadas, limpieza y producción final

**193 pruebas, 1.313 aserciones, cero errores, fallos u omisiones**, confirmados
por PHPUnit y JUnit; aproximadamente 64 segundos. Se incluyeron protocolo,
continuidad, autorización, raíces, transición, almacenamiento externo,
documentos e historial. Bootstrap `tests/documentos-isolated-bootstrap.php`,
PHP 8.4.15, `xdebug.mode=off`, memoria 512M solo para el proceso CLI y
`--do-not-cache-result`: SQLite `:memory:`, temporales, PDF ficticios y testigos
simulados. Ninguna prueba se conectó a MySQL real.

La restauración MySQL descrita arriba es un ensayo distinto, autorizado,
contra la instancia independiente; no se confundió con PHPUnit.

Se apagó limpiamente el servidor temporal mediante su endpoint y credencial
exclusivos; se comprobó ausencia del PID, de procesos con ese datadir y del
listener 43319. Se retiró únicamente `restore-work`: datos, credenciales,
certificados temporales y logs. Los tres archivos del respaldo nuevo se
conservan. No se eliminaron respaldos previos.

Comprobaciones finales de producción, exclusivamente de lectura:

- MySQL real operativo en 127.0.0.1:3306, misma identidad y esquema/datos.
- 29 tablas/128 filas y hashes intactos, 8 inscripciones, 6 referencias,
  cero documentos versionados y cero huérfanos.
- Los 109 PDF conservan rutas/hash; 103 sin asociación intactos.
- `.env`, configuración documental, discos, código previo, configuración
  MySQL/Apache y ACL observadas coinciden con el inicio.
- Servicios originales conservan estado, identidad y PID; no se modificaron.
  El SVG estático de `itm.test` respondió **HTTP 200**, sin una petición Laravel
  que pudiera crear una sesión real.
- Las cuatro opciones documentales y la barrera siguen **false**, caché real
  de configuración ausente y raíces privadas documentales vacías.
- Canal real bloqueado por el binding existente; no se emitieron permisos ni
  claves Ed25519 de producción. Los certificados/credenciales del MySQL
  desechable se limitaron al ensayo y fueron eliminados.

La única edición persistente dentro del proyecto es este informe. La carpeta
privada nueva retiene únicamente SQL, manifiesto y hashes. La limpieza de
temporales de PHPUnit se limita a nombres/patrones propios nuevos, comparados
con el inventario previo, bajo límites absolutos y sin seguir reparse points.
Se retiraron **95 raíces temporales nuevas** de la suite y los auxiliares,
inventarios de hashes y JUnit de `.phase19`; se conservaron temporales previos.
No se descartan cambios del usuario ni se realizan operaciones Git de escritura.

## 10. Requisitos pendientes y recomendación de siguiente fase

Siguen pendientes servicio testigo real, transporte mTLS, ancla independiente,
contrato/continuidad y decisión v2/v3, custodia Ed25519, identidades mínimas,
PKI y recuperación probada. También raíces/marcadores y publicaciones HTTP
aprobados, exclusión de escritores, ventana y ciclo de mantenimiento de la
barrera y autorización firmada de transición vigente.

Recomendar una fase posterior de **decisión de presupuesto, responsables y
proveedor**, seguida de implementación y validación del ancla/transporte en
laboratorio. Debe incluir cotización regional completa y aceptar explícitamente
si se usará un servicio sin HA con cierre ante caída. No desplegar por el
precio mínimo de catálogo ni autorizar transición por haber pasado el restore.

**Fase 2G-2B-1.9 terminada; el trabajo se detiene hasta nueva autorización.**

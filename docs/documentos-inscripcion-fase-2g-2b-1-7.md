# Fase 2G-2B-1.7 — cliente del testigo externo y pruebas aisladas

19 de octubre de 2026, America/El_Salvador.

Desarrollo, pruebas y evaluación documental exclusivamente. **El canal real
permanece bloqueado. No se desplegó un testigo ni se ejecutó la transición.**

## Resultado y archivos

Se implementó un cliente experimental del protocolo ITM v2 sobre una interfaz
de transporte sustituible. Se integró con el registro de consumos existente y
su espejo local, conservando las comprobaciones de autorización, continuidad,
identidad y exclusión. No se afirma compatibilidad con ningún servicio externo
existente: el contrato deberá revisarse al elegir la infraestructura real.

Archivos nuevos:

- `app/Contracts/TransporteTestigoDocumental.php`: frontera de comunicación autenticada y presupuesto temporal.
- `app/Exceptions/TestigoTiempoAgotado.php`: clasificación sanitizada de timeout.
- `app/Services/ProtocoloTestigoDocumental.php`: esquema, codificación canónica, dominios y Ed25519.
- `app/Services/AdaptadorTestigoDocumental.php`: desafíos, consumo, consulta y verificación del cliente.
- `tests/Support/TestigoProtocoloFicticio.php`: servidor simulado con PDO SQLite independiente.
- `tests/Feature/TestigoExternoDocumentalTest.php`: contrato, fallos, competencia y recuperación.
- Este informe.

Archivos modificados:

- `app/Services/AutorizacionTransicionDocumental.php`: entrega explícitamente lote y SID del contexto firmado al ticket.
- `app/Services/ConsumosAutorizacionDocumental.php`: transporta esos vínculos, verifica recibos v2 también en el espejo y conserva errores de incertidumbre/timeout.
- `tests/Feature/AutorizacionTransicionDocumentalTest.php`: integración del adaptador v2 con el permiso firmado y el motor SQLite; dos permisos validados no ejecutan dos transiciones.
- `tests/Feature/SolicitudesPendientesBadgeTest.php`: fixture de alumnos adultos y comprobación de aprobación sin errores para eliminar una dependencia aleatoria ajena al protocolo.

No se cambiaron el comando, el motor de transición, la barrera, el proveedor
de servicios ni las configuraciones documental y de discos. Se leyeron los
archivos exigidos y las dependencias de consumos antes de editar.

## Arquitectura y cierre del canal real

```text
Permiso Ed25519 externo
        |
AutorizacionTransicionDocumental
        | validación de entorno, artefactos, ventana y ejecutor
ConsumosAutorizacionDocumental ---- espejo local firmado + registro.json
        |
RegistroConsumosIndependiente
        |
AdaptadorTestigoDocumental (experimental)
        |
TransporteTestigoDocumental (interfaz; sin transporte de red entregado)
        |
TestigoProtocoloFicticio (solo tests; estado SQLite :memory: propio)
```

`AppServiceProvider` sigue registrando **RegistroConsumosNoDisponible**. No hay
selección por `.env`, fallback ficticio, opción `--force` ni negociación que
habilite producción. Incluso el adaptador experimental siempre rechaza
`exigirDisponibleParaCanalReal()` con
`REGISTRO_INDEPENDIENTE_REAL_PENDIENTE`. El testigo ficticio exige testing,
SQLite `:memory:` y ausencia de DB_URL en cada intercambio.

Se conserva el formato 2 del permiso de la fase 1.6. El número de formato del
protocolo del testigo es una versión independiente. La verificación antigua
de recibos formato 1 solo queda para las pruebas de caracterización de 1.6,
fuera del adaptador nuevo y prohibida fuera de testing. El cliente v2 nunca
acepta una degradación a recibos v1.

## Contrato ITM v2

Los mensajes son objetos planos. Se rechazan campos adicionales, ausentes,
tipos ambiguos, identificadores mal formados y versiones incompatibles.

| Campo base | Regla |
| --- | --- |
| registro_id | Identidad del testigo/registro, 32 caracteres hexadecimales, fijada por canal confiable |
| epoca | Generación del registro, 32 hex, provisionada independientemente; no se aprende del servidor |
| id | ID único de autorización, 32 hex |
| permiso_sha256 | SHA-256 del sobre exacto del permiso, 64 hex |
| vinculo_sha256 | SHA-256 del vínculo contexto, solicitud y clave del firmante, 64 hex |
| lote | Identificador explícito aprobado, 1–64 caracteres alfanuméricos, guion o subrayado |
| ejecutor_sid | SID de usuario Windows del contexto firmado; no acepta LocalSystem |
| desafio | Nonce fresco del cliente, 256 bits en 64 hex, nuevo en cada operación |

Estos ocho campos están presentes en solicitud inicial, desafío firmado y
recibo firmado. `registro_id` y `epoca` deben coincidir con los pins del cliente;
la clave pública Ed25519 fijada autentica al testigo. No hay confianza en una
clave anunciada por una respuesta ni descubrimiento automático de identidad.

1. **Desafiar:** el cliente envía los campos base más `formato=2` y
   `operacion=consultar|consumir` mediante `intercambiar('desafiar', ...)`.
2. **Vincular:** el testigo devuelve un sobre firmado de tipo `reto`, que
   reproduce toda la petición y añade `nonce` independiente de 256 bits,
   `desde` y `hasta` enteros UTC. La vigencia es exactamente 30 segundos.
   El servidor conserva la petición canónica completa vinculada al nonce.
3. **Operar:** el cliente verifica identidad, firma, coincidencia y vigencia;
   devuelve el mensaje de reto íntegro mediante `consultar` o `consumir`.
   El servidor exige coincidencia con el reto que emitió, identidad autenticada,
   vigencia y ausencia de uso previo del nonce.
4. **Recibir:** el servidor devuelve otro sobre de tipo `recibo`. Conserva
   los campos del reto y añade `estado`, `resultado`, `recibo_id` de 256 bits
   y `emitido_en`. La fecha debe estar dentro de la vigencia; el cliente
   también comprueba que aún está dentro de ella al recibir.
5. **Consultar incertidumbre:** se inicia una operación **consultar** con
   desafío nuevo y los mismos vínculos. Su resultado describe el estado;
   nunca acredita un consumo nuevo ni ejecuta una transición.

| Operación | estado | resultado | Significado |
| --- | --- | --- | --- |
| consultar | libre | consulta | No hay consumo en el registro independiente confiable |
| consultar | consumida | consulta | Ya existe consumo; no habilita ejecución |
| consumir | consumida | nuevo | Esta operación ganó el consumo atómico |
| consumir | consumida | rechazado | Otro consumo ganó antes; rechazo firmado y verificable |

Cualquier otra combinación falla cerrada. El adaptador verifica el recibo del
perdedor antes de generar `AUTORIZACION_CONSUMIDA`; nunca transforma un rechazo
en éxito idempotente. En el espejo solo se admite un recibo v2 de operación
`consumir`, estado `consumida` y resultado `nuevo`, vinculado al ticket completo.

La interfaz de transporte **debe autenticar al ejecutor fuera del cuerpo** y
aplicar límites de tamaño/plazo. La SID enviada no es una credencial. En el
ficticio se contrasta con un principal independiente fijado por el fixture.
No se implementaron TLS, mTLS, tokens, credenciales o autenticación de red.

## Canonicalización y Ed25519

El protocolo utiliza un subconjunto cerrado de JSON: objeto plano no vacío,
claves ASCII `[a-z_0-9]+` ordenadas byte a byte con `SORT_STRING`, valores
strings ASCII imprimibles o enteros no negativos, sin floats, booleanos,
null, listas u objetos anidados. Se usa JSON sin espacios y sin escapar `/`.
Los esquemas limitan por separado cada identificador y los campos temporales.
No se presenta esta codificación como una implementación general de JCS.

Ejemplo no sensible: `{"a":1,"z":"ascii/valor"}`. El payload recibido debe
coincidir byte a byte con su recodificación canónica: diferencias de orden,
espacios, escapes alternativos o claves duplicadas no se aceptan. Base64
de payload, firma y clave también debe coincidir con su recodificación.

Los sobres solo admiten `payload` y `firma`, ambos Base64. La firma separada
Ed25519 cubre los bytes:

```text
reto:   "ITM/testigo/v2/reto"   || byte NUL || JSON canónico
recibo: "ITM/testigo/v2/recibo" || byte NUL || JSON canónico
```

Un reto no sirve como recibo. Una firma del permiso documental tampoco sirve
como recibo del testigo. Se exige firma de 64 bytes, clave pública de 32 bytes
y payload de hasta 16 KiB. El transporte futuro debe limitar a 32 KiB el
sobre antes de decodificarlo. El cliente no solicita claves privadas.

Las claves de pruebas son efímeras y ficticias. La clave del testigo se genera
en el fixture independiente; los permisos documentales existentes se firman
con otro par de prueba. Ningún material de producción se creó o configuró.

## Consumo único y restauraciones

El ficticio tiene su propio PDO SQLite `:memory:` y tablas de retos/usos,
separadas de la conexión y almacenamiento Laravel. Cada instancia representa
un registro y época provisionados. La clave primaria del ID impide que cambiar
lote, huella o ejecutor cree otro consumo del mismo permiso. Si un ID ya existe
con otro vínculo, se rechaza; un ID desconocido en un registro desconocido
no se interpreta como libre.

Dentro de una transacción se marca el reto como usado mediante actualización
condicional, se consulta el consumo y se inserta el ganador con restricción
única. El recibo de resultado nuevo solo sale después del commit. No hay API
de borrado, reembolso, reinicialización ni desconsumo. La inserción del uso,
el vínculo y el identificador del recibo ganador forman la misma transacción.

La prueba de concurrencia usa dos Fibers que obtienen desafíos y quedan
suspendidas justo antes de decidir el consumo: ambas operaciones están en
vuelo simultáneamente. Se libera primero la segunda. Solo una obtiene
`nuevo`; la otra recibe `rechazado` firmado y verificado. Queda exactamente
un uso. Se prueban intercalaciones deterministas de un único proceso, **no
procesos distribuidos ni conexiones a un servidor de producción**. El esquema
real necesitará ensayos multiproceso y pruebas de su aislamiento transaccional.

El cliente consume remotamente antes de crear el espejo con apertura
exclusiva, escritura completa, fflush y fsync. Después vuelve a verificar
espejo y estado fresco. Borrar, sustituir o restaurar recibos y recrear el
cliente no revierte el PDO del ficticio: las pruebas exigen rechazo. Un espejo
v2 no se considera evidencia vigente por su edad; su firma es evidencia del
consumo histórico y se exige adicionalmente una consulta remota fresca.

No hay transacción distribuida entre testigo, archivos y SQL. La ventana y
la identidad del permiso se siguen comprobando antes de ejecutar y durante
la acción; los fallos posteriores al consumo gastan el permiso. El mecanismo
de exclusión y las reglas del motor documental no se cambiaron.

## Comunicación, plazos e incertidumbre

Presupuesto **total de 5.000 ms**, medido con reloj monotónico, para desafío
y operación juntos. Al segundo intercambio se entrega solo el tiempo restante.
El transporte debe imponer ese plazo a conexión y lectura; además el cliente
rechaza una respuesta tardía. El reloj UTC de vigencia es independiente del
monotónico. Las pruebas sustituyen ambos sin dormir ni abrir puertos.

La interfaz no puede interrumpir por sí sola una implementación que bloquee
sin respetar el plazo. Esto debe verificarse al escribir el transporte real;
no se promete timeout efectivo de sockets porque no se entregaron sockets.

| Situación | Resultado y recuperación |
| --- | --- |
| Falla la obtención del reto | TESTIGO_COMUNICACION_FALLIDA o TESTIGO_TIEMPO_AGOTADO; no se envía consumo |
| Se pierde la conexión/expira el plazo al enviar consumir | CONSUMO_REMOTO_INCIERTO; no iniciar acción ni reintentar automáticamente |
| El servidor consumió y perdió la respuesta | Mismo estado incierto; consulta posterior fresca devuelve consumida |
| Firma, esquema o vínculo incorrecto tras enviar consumir | Rechazo criptográfico/de protocolo; también puede haber consumo remoto: detener y reconciliar |
| Segundo consumo | Rechazo firmado, convertido en AUTORIZACION_CONSUMIDA tras verificarlo |
| Persistencia remota falla | No se entrega éxito; el cliente conserva incertidumbre hasta consulta verificada |
| Persistencia local falla después del consumo | No se libera el permiso ni se borra evidencia; revisión administrativa |
| Consulta no verificable o servidor sin continuidad | Cierre; nunca presumir libre ni cambiar época automáticamente |

No existe bucle de reintento en el adaptador. Las excepciones de transporte
se sustituyen por códigos sin mensajes originales, URLs, secretos o trazas.
El código nuevo no escribe logs. Consultar `consumida` no recupera el derecho
de ejecutar ni reconstruye automáticamente un espejo ausente. Una consulta
`libre` después de una incidencia tampoco ejecuta nada: exige revisión y
validación explícita conforme al procedimiento autorizado.

## Identidades y límites de confianza

| Rol | Responsabilidad y derechos previstos |
| --- | --- |
| Firmante | Custodia fuera del host la clave privada de permisos; no comparte clave con el testigo |
| Testigo | Clave privada de recibos y registro persistente bajo administración separada; no administra Laravel |
| Ejecutor Artisan | Identidad autenticada de consulta/consumo; lee clave pública y permiso; sin DELETE, reinicio de época o administración del testigo |
| Apache/LocalSystem | Sin credenciales de firmante, testigo ni administración externa |

Esto es diseño pendiente de implementación operativa. LocalSystem conserva
privilegios locales amplios; las ACL existentes no demuestran separación de
secretos frente a él. No se cambiaron ACL ni cuentas. En la inspección elevada
el proceso de herramientas acreditó `High Mandatory Level` y Administradores
habilitado; eso permite las lecturas privadas de auditoría, no habilita la
transición ni se usa como identidad de un testigo real.

El ficticio no sobrevive a la caída del proceso PHP, no ofrece alta
disponibilidad, autenticación de red, retención inmutable ni defensa frente al
administrador de su proceso. Restaurar simultáneamente cliente y testigo
eliminaría la independencia requerida. Una firma prueba origen/integridad,
no persistencia ni ausencia de rollback. No se considera resuelto ese riesgo.

## Infraestructura futura: evaluación documental

Investigación de documentación primaria consultada el 19 de octubre de 2026.
Los costos siguientes son estimaciones cualitativas de operación, no tarifas
o presupuestos comerciales. No se crearon cuentas, servidores o contenedores.

| Criterio | Otro equipo con administración separada | Servicio externo con almacenamiento persistente | Base transaccional independiente con identidad separada |
| --- | --- | --- | --- |
| Independencia | Adecuada solo si administradores, backups y credenciales quedan fuera de ITM/LocalSystem | Cuenta/proyecto y administradores separados; compartir la cuenta principal debilita la frontera | Instancia/host y administración separados; otra tabla del MySQL actual no basta |
| Persistencia | Disco, sincronización y backups auditados a cargo del operador | Almacenamiento gestionado; políticas de retención/continuidad propias siguen siendo necesarias | WAL, commits duraderos y respaldo/replicación configurados y probados |
| Consumo atómico | Servicio con transacción y restricción única | Operación condicional o transacción; región y consistencia explícitas | INSERT exclusivo por ID con UNIQUE; conflicto es rechazo, nunca UPDATE del consumo |
| Claves | Servicio aislado; almacén de claves externo al ejecutor y acceso mínimo | Custodia separada; confirmar soporte Ed25519 del almacén/HSM elegido | La base no reemplaza el firmante de recibos: requiere servicio con clave separada |
| Disponibilidad | Depende de energía, red y repuesto/replicación propios | Dependencia de proveedor, región y conectividad; diseñar cierre ante caída | Réplicas/failover bajo operación propia o gestionada; evitar lecturas atrasadas |
| Recuperación | Restauración supervisada y reconciliación de todos los consumos posteriores | PITR/backups no garantizan no rollback; impedir reasignación silenciosa de época y claves | Recuperar WAL hasta estado confiable y reconciliar antes de permitir libre |
| Costos operativos | Equipo, energía, backup, monitoreo y horas de administración | Cobro recurrente de cómputo, almacenamiento, peticiones, claves, backup y alertas | Instancia/licencia si aplica, almacenamiento, copias y personal DBA/operación |
| Mantenimiento | Mayor responsabilidad local: parches, continuidad, claves y certificados | Menos operación de discos, más IAM, dependencia de proveedor y revisión de políticas | Complejidad de DB más servicio/protocolo; credenciales mínimas y procedimientos auditados |

Como mecanismo concreto posible para un servicio externo, DynamoDB documenta
inserción condicional con `attribute_not_exists`; una escritura sin condición
puede reemplazar un elemento. El diseño deberá combinar la decisión y el reto
sin ventanas entre registros, y usar lecturas fuertes en la tabla apropiada.
La documentación distingue lecturas eventuales y fuertes; no se asume que un
índice o una réplica ofrezcan la consistencia necesaria.
[PutItem](https://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_PutItem.html),
[consistencia](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/HowItWorks.ReadConsistency.html).

PostgreSQL documenta restricciones únicas y `ON CONFLICT DO NOTHING`, con
`RETURNING` para distinguir lo realmente insertado. Es una base posible del
CAS; no debe usarse UPSERT para reabrir o sobrescribir un ID consumido. La
durabilidad requiere revisar la configuración WAL/fsync y probar recuperación;
tener una transacción no basta si se relaja la persistencia.
[INSERT](https://www.postgresql.org/docs/18/sql-insert.html),
[WAL](https://www.postgresql.org/docs/18/runtime-config-wal.html).

Las cuentas de servicio Windows permiten asignar un contexto de seguridad al
servicio; seleccionar una cuenta no crea por sí mismo independencia frente al
administrador del equipo. Otro equipo deberá tener administración separada,
no solo otro proceso LocalSystem.
[Microsoft: cuentas de servicio](https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/manage/understand-service-accounts).

Tanto PITR PostgreSQL como las copias a un punto temporal de DynamoDB sirven
para recuperar estados anteriores. **Esa capacidad también exige un control
operativo contra el retorno de autorizaciones gastadas**: restaurar un backup
anterior y seguir sirviendo libre con la misma identidad sería inseguro.
[Recuperación PostgreSQL](https://www.postgresql.org/docs/18/continuous-archiving.html),
[copias temporales DynamoDB](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Point-in-time-recovery.html).

Recomendación de diseño: **servicio dedicado bajo administración separada,
respaldado por una base transaccional independiente**. Preferir almacenamiento
gestionado si existe un operador responsable y presupuesto aprobado; un equipo
separado es viable si se garantiza continuidad y recuperación. Esta elección
es una inferencia de los requisitos de ITM, no una certificación de un proveedor.
Un acceso SQL directo desde Artisan no entrega por sí mismo los recibos
Ed25519 ni protege la clave: debe permanecer la frontera de servicio/testigo.

## Pruebas y verificación final

Primer pase focalizado: **139 pruebas, 948 aserciones, cero fallos** sobre
autorización, raíces, transición y testigo. Pase dedicado de la versión final
del protocolo antes del refuerzo final: **29 pruebas, 121 aserciones, cero
fallos**. Pase final focalizado (30 casos de protocolo, integración con
autorización/motor y 18 casos de badge): **49 pruebas, 196 aserciones, cero
fallos**. Los diez archivos PHP nuevos/modificados superaron `php -l`.

Cobertura del protocolo: orden canónico y tipos rechazados, separación de
dominios, desafíos distintos, consumo y segundo consumo, solicitudes
solapadas, respuesta perdida después del commit, reinicio del cliente,
consulta posterior, desconexión, timeout antes/después de consumir,
presupuesto monotónico compartido, desafío vencido/reutilizado, firma o clave
incorrecta, respuesta duplicada, autorización/lote/ejecutor incorrectos,
testigo desconocido, campos adicionales/ausentes, versión incompatible,
estado ambiguo, JSON no canónico con firma correcta (orden, espacios y claves
duplicadas), Base64 alternativo, fallo de persistencia y recibos locales borrados,
restaurados o sustituidos.

La regresión anterior mantiene pruebas de recreación de la aplicación,
permiso firmado reutilizado, identidad cambiada, simulación Artisan, motor
SQLite, rollback, reanudación con permiso nuevo, lote ficticio de 6 asociados
y 103 sueltos, descargas y barrera real cerrada antes de alcanzar MySQL.

Comando de regresión completa (PHP 8.4.15; opciones solo para este proceso):

```text
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --do-not-cache-result
```

El bootstrap exige SQLite `:memory:` efectivo, elimina DB_URL y fuerza
almacenamiento temporal antes de migrar las tablas ficticias. No se modificó
`php.ini`, `.env`, caché real ni configuración persistente para ejecutar tests.
No se abrieron puertos, iniciaron servicios o conectaron pruebas a MySQL.

El primer pase completo ejecutó 1.163 pruebas y 6.428 aserciones: 1.162 pasaron
y falló un caso anterior del contador de solicitudes. La reproducción aislada
falló en el otro caso de aprobación del mismo archivo. La causa está en el
fixture: `AlumnoFactory` genera fecha de nacimiento aleatoria; si resulta menor
y no tiene encargado, `EncargadoInscripcion::exigir()` rechaza correctamente
la aprobación y el contador no baja. Un redirect por sí solo no prueba éxito.

Se corrigió exclusivamente el fixture de ese test para crear adultos ficticios
de 20 años y exigir ausencia de errores de sesión al aprobar. No se cambiaron
controlador, reglas de encargado, factory global ni flujo de inscripción.
Los 18 casos pasan aislados con 56 aserciones. Se repitió la regresión completa
con los dos casos finales adicionales de protocolo/integración.

**Regresión completa final: 1.165 pruebas, 6.449 aserciones, cero errores,
fallos u omisiones**, confirmados por salida de PHPUnit y JUnit. Duración
aproximada de 10 minutos y 16 segundos. Esta pasada incluye el fixture
corregido, los 30 casos nuevos de protocolo y la integración completa v2.

Comparación final mediante proceso de auditoría separado de los tests:
MySQL en transacción `READ ONLY` terminada con rollback, hashes de filas
ordenados, rutas/SHA-256 de PDF y lecturas de configuración/ACL/servicios.

- Las **29 tablas** conservan conteos y hashes de todas sus filas.
- Permanecen **8 inscripciones y 6 referencias**; `documentos_inscripcion`
  conserva **cero filas**.
- Los **109 PDF** conservan rutas y SHA-256 frente al inicio de esta fase.
- Las opciones `enabled`, `history_enabled`, `legacy_read_enabled` y
  `transition_guard_enabled` continúan **false**; caché de configuración ausente.
- Las cinco raíces privadas revisadas conservan ACL e inventarios: documentos,
  cuarentena, autoridad y bloqueos vacíos; el padre de almacenamiento privado
  solo contiene `inscripciones`. No apareció `usos`, claves o permisos reales.
- Coinciden hashes/SDDL de `.env`, `filesystems.php`, packages/services y los
  siete archivos Apache/aliases/hosts observados. Los otros discos Laravel
  mantienen su huella de configuración y las raíces reales no cambiaron.
- Los servicios Apache y MySQL conservan identidad, estado y PID del servicio.
  No hubo recarga, reinicio o modificación de infraestructura.

No se realizaron operaciones Git ni worktrees, migraciones/seeders reales,
escrituras MySQL, cambios de ACL, traslado/eliminación de originales,
habilitación documental, transición real o provisión de material criptográfico
de producción. Las migraciones de la suite se limitaron a SQLite en memoria.

Contraste histórico adicional, de solo lectura: se verificó la huella del
inventario privado de fase 2D,
`1592f563a0add3643a8b145fe2901361bfe213597b5167776523386e723ce283`,
y se compararon sus 109 pares ruta/SHA-256 con la lectura inicial actual.
Coinciden los 109 originales, incluidos los 103 sin asociación en las mismas
ubicaciones. No se copiaron respaldos ni PDF al proyecto; los auxiliares
contienen exclusivamente código e inventarios temporales de hashes.

Se retiraron **822 raíces temporales nuevas** de los distintos pases de prueba,
contrastadas con el inventario anterior. La limpieza exigió nombres aleatorios
con patrones exactos y rutas absolutas hijas directas de TEMP, sin seguir
junctions/reparse points. Se conservaron los temporales preexistentes. También
se retiraron los auxiliares e inventarios/JUnit de `.phase17`; no se deja
material criptográfico ficticio nuevo en el proyecto. Los resultados
persistentes son los **11 archivos** de código, pruebas e informe enumerados
arriba (10 PHP y este documento).

## Condiciones pendientes antes de producción

1. Autorizar y desplegar infraestructura realmente independiente, con responsables, presupuesto y administración separados.
2. Revisar el protocolo experimental y construir transporte autenticado con límites reales, TLS y principal vinculado al permiso; probar timeouts y cancelación sin reintentos de mutación.
3. Implementar el servicio persistente, consultas linealizables, consumo y reto atómicos, restricción única, recibos, continuidad de época e identidad; probar competencia multiproceso y fallos reales.
4. Separar firmante/testigo/ejecutor/web, custodiar claves distintas y provisionar pins por canal independiente. Revisar ACL mínimas en una fase autorizada.
5. Definir retención de consumos, auditoría, recuperación sin rollback lógico, reconciliación y rotación/revocación. Probar restauraciones y pérdida de infraestructura sin volver a emitir libre indebidamente.
6. Completar las precondiciones anteriores: raíces y marcadores aprobados, inventario HTTP, respaldo actualizado de 29 tablas con restauración aislada verificada, auditoría de escritores y mantenimiento/recuperación de la barrera.

Los 109 originales continúan en la publicación histórica; esta fase no cambia
su ubicación ni exposición. No se provisionan permisos de producción. Un pase
de pruebas no autoriza transición, despliegue ni avance de fase.

Fase 2G-2B-1.7 terminada. El trabajo se detiene aquí hasta nueva autorización.

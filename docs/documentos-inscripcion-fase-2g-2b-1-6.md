# Fase 2G-2B-1.6 — autorizaciones y protección contra reutilización

19 de octubre de 2026, America/El_Salvador. Desarrollo, pruebas aisladas y
documentación. **El canal real continúa cerrado; no se ejecutó transición.**

## Resultado y alcance

Se sustituyó la confianza en la existencia de un archivo de consumo por un
protocolo que exige un testigo independiente, con respuestas Ed25519 a desafíos
nuevos, consumo atómico irreversible y un espejo local verificable. La parte
cliente del protocolo está implementada y probada. **No se entrega un servicio
externo real ni se considera satisfecha su dependencia de almacenamiento.**

AppServiceProvider registra RegistroConsumosNoDisponible como único adaptador
de producción entregado. Este rechaza el canal real. Configurar rutas, cambiar
la barrera o agregar una variable permanente no proporciona un adaptador ni
habilita la transición. No existen opciones --force ni un fallback a registros
locales cuando el testigo falla.

Se revisaron completos los servicios y comando indicados, configuración,
pruebas existentes e informe 1.5 antes de modificar su implementación. Se
conservaron simulación, motor SQLite, comprobaciones de artefactos/ventana,
autorización de documentos, conservación de originales e idempotencia del motor.

## Riesgos corregidos en código

| Riesgo anterior | Comportamiento nuevo |
| --- | --- |
| autoridad() dependía de LOCALAPPDATA | Exige authorization_root explícita; no consulta ese entorno ni calcula cuarentena/consumos como hermanos implícitos. |
| Borrar id.consumida permitía replay | El testigo conserva el consumo; la ausencia del espejo produce rechazo. |
| Un archivo existente no acreditaba integridad | Se verifica firma del recibo y su vinculación a ID, huella del permiso, contexto, registro y época. |
| Restaurar estado local anterior | Una consulta fresca al testigo conserva la evidencia del consumo; una recreación de la aplicación no lo olvida. |
| Reutilizar respuestas remotas antiguas | Cada consulta/reclamación lleva un desafío aleatorio nuevo de 256 bits, incluido en la respuesta firmada. |
| Cambiar ejecutor o registro | El formato 2 firma SID ejecutora, raíz de consumos y huella de la configuración del registro. Se comprueban antes de consumir y durante la acción. |
| Cruce del vencimiento durante una consulta remota | Se vuelve a comprobar la ventana después de consumir y antes de iniciar la acción. El ID queda gastado aunque ya no pueda ejecutarse. |
| Raíces ambiguas o sustituidas | Preflight con rutas absolutas, publicaciones explícitas, no solapamiento, rechazo de enlaces y marcadores de identidad con huellas aprobadas. |

El cambio a **formato 2** es deliberado: permisos de formato 1 no satisfacen
las nuevas garantías y se rechazan. No se convierten ni vuelven a firmar
automáticamente. Las versiones de manifiesto y auditoría documental se
conservan; no se reescriben inventarios reales.

## Fuente de confianza y protocolo

RegistroConsumosIndependiente define tres operaciones: comprobar disponibilidad
para el canal real, consultar y consumir. El adaptador futuro debe autenticar
al ejecutor, mantener un registro/época provisionados independientemente y
ofrecer consultas linealizables y compare-and-set irreversible por
(registro_id, época, ID). El perdedor concurrente debe fallar; no puede recibir
un éxito idempotente que el cliente confunda con un consumo nuevo.

La integridad y continuidad globales del testigo deben estar verificadas antes
de responder libre. Un registro/época desconocidos, un historial perdido o una
restauración no pueden interpretarse como ausencia de consumos. La firma de
una respuesta acredita quién respondió; **no demuestra por sí sola durabilidad,
independencia, no rollback o corrección del servidor**.

La implementación cliente opera así:

1. Verifica el sobre Ed25519 de autorización, huella de clave, entorno,
   inventario, respaldo, auditoría y ventana como antes, más los nuevos vínculos.
2. Exige que exista registro.json local con versión, registro_id, época y huella
   de la clave pública del testigo iguales a la configuración confiable. No lo
   crea, repara ni aprende por primera observación.
3. Consulta al testigo con ID, huella completa del sobre, huella del vínculo
   entorno/solicitud/clave y desafío nuevo. Comprueba firma y coincidencia de
   todos esos campos antes de aceptar libre o consumida.
4. Contrasta la respuesta con el espejo. Un recibo local corrupto, sustituido,
   vacío o contrario al estado remoto produce fallo cerrado. Consumida sin
   espejo también se rechaza; la ausencia no se interpreta como permiso nuevo.
5. Antes de ejecutar solicita el consumo atómico remoto. Esa decisión ocurre
   **antes** de crear el espejo local y antes de iniciar la acción documental.
6. Guarda el recibo firmado con apertura exclusiva xb, escritura completa,
   fflush y fsync obligatorio. Verifica de nuevo espejo y testigo. Si hay fallo,
   no elimina el estado ni considera que puede reintentar el mismo ID.
7. Comprueba identidad, ventana y consistencia durante exigirActiva() y al
   terminar la acción. Si se pierde el testigo o se altera el espejo se detiene.

El espejo id.consumida contiene el sobre firmado del testigo; no una clave
secreta, un booleano confiado o una autofirma del ejecutor. registro.json es
un enlace de continuidad contrastado con configuración previamente aprobada,
no una autoridad independiente. Los consumos se vinculan al sobre exacto:
cambiar su contenido, firma, contexto o raíz requiere otra autorización.

## Límites frente a administradores

El protocolo detecta borrado/restauración local mientras el testigo conserva
su estado independiente y confiable. Si un administrador puede restaurar
**todos** los estados, incluido el testigo, con la misma identidad/época y
claves, esa condición deja de cumplirse. Tampoco se protege un verificador
que el administrador pueda reescribir. No se afirma que dos archivos, una
cadena de hashes o firmas locales resuelvan esa amenaza.

El testigo real y su administración deben estar fuera de la frontera de
privilegios de Apache/SYSTEM y del ejecutor local. Sus mecanismos de
durabilidad, recuperación, retención y prevención de rollback quedan pendientes
de elección, implementación y validación independiente. No se desplegó red,
servicio, almacenamiento externo o adaptador real en esta fase.

## Rutas e identidades explícitas

| Configuración | Fuente preparada | Valor entregado |
| --- | --- | --- |
| authorization_root | INSCRIPCION_AUTORIDAD_ROOT | Sin configurar |
| permits_root | INSCRIPCION_PERMISOS_ROOT | Sin configurar |
| consumption_root | INSCRIPCION_CONSUMOS_ROOT | Sin configurar |
| quarantine_root | INSCRIPCION_CUARENTENA_ROOT | Sin configurar |
| transition_control_root | INSCRIPCION_TRANSICION_CONTROL_ROOT, ya existente | Se conserva la selección actual; el canal real exige valor explícito |
| executor_sid | INSCRIPCION_EJECUTOR_SID | Sin configurar |
| http_roots | Inventario privado aprobado de todas las publicaciones | Lista vacía; bloquea preflight real |
| root_identity_sha256 | Huellas aprobadas de los marcadores .itm-raiz | Mapa vacío; bloquea preflight real |
| consumption_registry | ID, época y clave pública del testigo | Valores null; sin confianza implícita |

No se editaron .env ni sus valores. El disco inscripciones y los demás discos
conservan su configuración. No se instalaron valores personales en código.

RutasAutorizacionDocumental verifica seis raíces: autoridad, permisos,
consumos, cuarentena, barrera y documentos privados. Todas deben existir,
ser absolutas y estar fuera de repositorio, www, public, disco público y
publicaciones declaradas. Se rechazan solapamientos también entre raíces
privadas, marcadores sin pin, pequeños/vacíos, alterados o repetidos, rutas
relativas, letras de unidad relativas, UNC, placeholders %, $, enlaces y
ancestros cuyo realpath redirige. Un permiso debe estar en la raíz de permisos
aprobada. No hay creación automática de carpetas o marcadores.

Las rutas canónicas y huellas de marcadores forman un vínculo incluido en el
contexto del canal real. Los marcadores identifican el destino aprobado; no
son una prueba de inmutabilidad frente a un administrador que clone o sustituya
todo el estado. El testigo y la protección operativa siguen siendo necesarios.

El fallback histórico del directorio de bloqueos se conserva para funciones
existentes y pruebas. No se permite utilizarlo como selección implícita del
preflight real. La barrera sigue deshabilitada por defecto.

IdentidadEjecutorDocumental obtiene la SID del token del proceso mediante
whoami, no USERNAME, LOCALAPPDATA o una SID declarada por el cliente. Exige
coincidencia con la configuración y el contexto firmado; no acepta LocalSystem
como usuario ejecutor. Las pruebas sustituyen únicamente el proveedor de SID
para simular cambios, sin crear usuarios reales.

## Separación de funciones y permisos mínimos

- Firmante: custodia la clave privada de permisos fuera del host; no necesita
  Apache ni acceso de escritura a Laravel. No se generó esa clave.
- Administrador de confianza: instala la clave pública y aprueba su huella,
  las raíces y el registro/época por canal independiente. El ejecutor solo lee
  esos valores aprobados.
- Ejecutor Artisan: lee permiso/artefactos y originales, escribe destinos
  autorizados y espejo, utiliza bloqueos y reclama consumos al testigo mediante
  una identidad con permisos limitados. No administra ni revierte el testigo.
- Testigo: conserva su estado y clave privada de recibos fuera del ejecutor;
  autentica al reclamante y no ofrece operaciones de desconsumo o reinicialización
  al cliente. Su clave es diferente de la del firmante de permisos.
- Apache/PHP: no firma, no administra autoridad o épocas y no modifica el
  registro independiente. Solo debe obtener los derechos documentales que
  requiera la aplicación cuando se apruebe el flujo correspondiente.

Las ACL reales actuales no se cambiaron y siguen permitiendo FullControl a
SYSTEM, usuario y Administradores. No se presentan como la separación mínima
ya conseguida. LocalSystem tiene privilegios que impiden tratar una ACL local
como frontera suficiente frente al proceso web; se necesita separación real
de identidades/administración, a autorizar fuera de esta fase.

## Reglas de fallo cerrado

Se rechaza la ejecución cuando faltan adaptador real, confianza en el testigo,
metadatos de continuidad, identidad, pin de raíz o inventario HTTP; cuando la
firma, huella, ventana o respuesta no corresponde; cuando existe un consumo o
discrepancia local/remota; ante una respuesta antigua, estado desconocido,
interrupción de comunicación, imposibilidad de sincronizar el recibo, cambio de
configuración/identidad o ruta redirigida. Nunca se recrea automáticamente un
registro ausente ni se decide libre por fallo del testigo.

El adaptador entregado genera REGISTRO_INDEPENDIENTE_REAL_PENDIENTE para el
canal real. Su API de lectura/consumo tampoco ofrece almacenamiento de reserva.
La comprobación se realiza antes de abrir MySQL para preparar la transición.
Los cierres anteriores de entorno testing, barrera desactivada y autorización
obligatoria permanecen. El comando conserva sus mensajes sanitizados y sus
parámetros existentes, sin atajos nuevos.

## Recuperación

| Punto de interrupción | Regla |
| --- | --- |
| Antes del consumo remoto | No se inicia la acción; corregir la condición y volver a validar dentro de la ventana. |
| Consumo remoto confirmado pero respuesta perdida | Estado ambiguo para el cliente: detenerse. El testigo ya lo conserva como consumido. No ejecutar por presumir que falló antes de consumir. |
| Consumo remoto sin recibo local verificable | El mismo ID se rechaza; no reconstruirlo automáticamente ni borrar su evidencia. Reconciliación administrativa y autorización nueva. |
| Durante copia o transacción | Conservar bloqueo, originales, recibos, copias y .part. La compensación de archivos y el rollback documental mantienen las reglas anteriores. |
| Después del commit, antes del marcador final | Comprobar SQL y archivos; no inferir rollback. La reanudación debe tener permiso de ID nuevo y plan compatible. |
| Restauración local antigua o reinicio del cliente | Consultar al testigo; nunca reaprender el estado a partir del espejo restaurado. |
| Pérdida/rollback del testigo | No emitir libre ni cambiar de época para ocultarlo. Mantener el canal cerrado hasta recuperar y auditar su continuidad independiente. |

El fallo también gasta el permiso una vez decidido el consumo independiente.
Una reanudación requiere nueva autorización, ID distinto, auditoría/respaldo
actualizados y mismo lote/plan solo si sus datos siguen coincidiendo. No se
eliminan consumos previos. La idempotencia documental evita duplicar versiones
idénticas; no convierte un permiso gastado en reutilizable.

No hay transacción única entre testigo, NTFS y MySQL. Las pruebas SQLite
comprueban rollback e idempotencia del motor; no se ensayaron caídas o
recuperaciones de datos reales. La barrera real y su ciclo de salida siguen
requiriendo diseño/aprobación de la fase anterior.

## Pruebas

Regresión completa de Feature: **1.134 pruebas, 6.307 aserciones, cero fallos,
errores u omisiones**. Se ejecutó con PHP 8.4.15 de Wamp y el bootstrap que
comprueba PDO SQLite :memory: y fuerza almacenamiento temporal antes de las
migraciones de prueba:

```text
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --do-not-cache-result
```

El JUnit temporal confirmó esos conteos. Los 13 archivos PHP nuevos o
modificados también pasaron comprobación de sintaxis. No se ejecutó Composer
test ni una limpieza global de configuración.

Cobertura nueva o reforzada: firmas válidas/inválidas de permisos y recibos,
clave pública incorrecta, sobre alterado/antiguo/expirado, expiración durante
consumo, ID ya usado, recibo borrado/vacío/alterado/sustituido, continuidad
ausente/cambiada, restauración local y externa incoherente, respuesta repetida,
caída y respuesta perdida del testigo, dos reclamantes, cambio de identidad,
recreación de la aplicación, raíces relativas/ambiguas/superpuestas/publicadas,
marcadores sustituidos/repetidos y junction real de prueba en Windows.

La prueba de 1.5 que demostraba replay por borrado se convirtió en una regresión
que exige rechazo. Se mantienen simulación, ejecución SQLite ficticia, rollback,
reanudación con permiso nuevo, descargas autorizadas y conservación documental.

RegistroConsumosFicticio existe solo en tests/Support, usa otro PDO SQLite
:memory: y claves en memoria. Su estado queda separado del almacenamiento
Laravel durante las simulaciones de restauración/reinicio; no es almacenamiento
duradero ante caída del proceso y **no se ofrece como adaptador de producción**.
Rechaza operaciones fuera de testing/SQLite y siempre rechaza disponibilidad
para el canal real, incluso si se inyecta accidentalmente.

No se cambió php.ini. Un primer intento de regresión completa agotó los 128 MB
del proceso al construir una imagen en DirectorFirmaDibujadaTest, después de
401 pruebas terminadas. La salida parcial no se contó como suite completa.
Se repitió con memory_limit=512M y xdebug.mode=off exclusivamente en ese proceso
CLI. Un pase inicial de seguridad detectó una expectativa de barras sin
normalizar en Windows; se corrigió la expectativa y se verificó de nuevo.

## Archivos modificados

Código nuevo:

- app/Contracts/RegistroConsumosIndependiente.php.
- app/Services/RegistroConsumosNoDisponible.php.
- app/Services/ConsumosAutorizacionDocumental.php.
- app/Services/IdentidadEjecutorDocumental.php.
- app/Services/RutasAutorizacionDocumental.php.

Código actualizado:

- app/Services/AutorizacionTransicionDocumental.php: formato 2 y coordinación del consumo verificado.
- app/Services/EntornoTransicionReal.php: rutas explícitas, vínculos y cierre por dependencia pendiente.
- app/Services/TransicionDocumental.php: raíz explícita de consumos.
- app/Providers/AppServiceProvider.php: adaptador por defecto no disponible.
- config/documentos_inscripcion.php: precondiciones sin valores ni habilitación funcional.

Pruebas y documentación:

- tests/Support/RegistroConsumosFicticio.php.
- tests/Feature/AutorizacionTransicionDocumentalTest.php.
- tests/Feature/RutasAutorizacionDocumentalTest.php.
- Este informe.

No se modificaron config/filesystems.php, ControlTransicionDocumental, el
comando CLI, .env, infraestructura, permisos Windows o documentos reales.

## Verificación final

Comparación inicial/final mediante lecturas MySQL en transacción READ ONLY,
terminada con rollback, y hashes SHA-256 de archivos:

- Las 29 tablas conservan conteos y hashes deterministas de sus filas.
- Permanecen las 8 inscripciones y las 6 referencias; documentos_inscripcion
  sigue vacía.
- Los 109 PDF conservan rutas y SHA-256; no se copiaron, trasladaron, modificaron
  o eliminaron originales. Los 103 sin asociación no recibieron asociaciones.
- Las raíces documentales, cuarentena, autoridad y bloqueos reales siguen
  vacías; el padre PrivateStorage solo contiene inscripciones. usos sigue
  inexistente. No se provisionaron marcadores, claves o autorizaciones reales.
- Las cuatro opciones documentales y la barrera permanecen false.
- .env, filesystems.php, packages/services, los siete archivos Apache/hosts
  y sus ACL coinciden con los hashes/SDDL iniciales. También coinciden las ACL
  y los inventarios de las cinco raíces privadas revisadas.
- Los otros discos Laravel no cambiaron. La caché real de configuración sigue
  ausente; bootstrap/cache conserva únicamente sus archivos previos.
- Apache y MySQL siguen disponibles; los estáticos de itm.test y localhost
  respondieron 200 después de las pruebas. No hubo recarga o reinicio.

Se retiraron 250 raíces temporales nuevas de los distintos pases, comparadas
con el inventario anterior y patrones aleatorios exactos de la suite. La
limpieza verificó rutas absolutas bajo TEMP y no siguió reparse points. Se
conservaron temporales preexistentes. También se eliminaron auxiliares,
inventarios de hashes y JUnit temporales de esta fase. No se crearon respaldos
de datos reales ni rutas web de diagnóstico.

No se realizaron operaciones Git, worktrees, cambios de ACL, actualizaciones
de servicios o recuperación sobre datos reales. Los cambios persistentes son
los 14 archivos de código, pruebas, configuración de código y documentación
enumerados arriba, no configuración local de despliegue.

## Dependencias pendientes y siguiente fase

Antes de autorizar una transición se debe implementar e integrar el adaptador
independiente real, revisar su autenticación, consumo atómico, recuperación,
durabilidad y prevención de rollback. Requiere una frontera de privilegios
externa, clave pública de testigo aprobada, registro/época provisionados y
pruebas de consistencia y fallos bajo las identidades definitivas. No basta
con reemplazar el adaptador por uno que declare disponibilidad.

También siguen pendientes las ACL/identidades mínimas, raíces y marcadores
aprobados, autoridad pública y firmante fuera del host, respaldo actualizado
de 29 tablas con restauración aislada probada, manifiesto actual, exclusión
de escritores externos y ciclo autorizado de mantenimiento/recuperación.
Los 109 originales siguen públicos; esta fase no cambia su publicación.

Se recomienda una siguiente fase de integración y validación del testigo
independiente y de las políticas de despliegue en entorno aislado. Provisionar
infraestructura, generar claves reales o ejecutar la transición requiere
autorización posterior específica. No se dejan permisos reales preparados.

Se detiene el trabajo al entregar este informe.

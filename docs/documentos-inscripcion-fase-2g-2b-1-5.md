# Fase 2G-2B-1.5 — auditoría final y propuesta de transición

19 de octubre de 2026, America/El_Salvador. Auditoría de lectura del entorno
real, documentación y pruebas aisladas. **Dictamen: NO ejecutar todavía.**

## Hallazgos y condiciones pendientes

El inventario, el almacenamiento externo y las protecciones HTTP revisadas
conservan el estado esperado. La firma y el consumo exclusivo funcionan bajo
sus condiciones de confianza, pero no bastan para autorizar una transición:

| Prioridad | Hallazgo | Condición antes de ejecutar |
| --- | --- | --- |
| Bloqueante | SQL de fases 2D/2F cubre 28 tablas; hoy existen 29. Ambos registran restauración no probada. | Respaldo actualizado de estructura y datos de las 29 tablas, restaurado y verificado en instancia aislada. |
| Bloqueante | Autoridad deriva de LOCALAPPDATA; Apache y Artisan tienen valores distintos. No existen clave pública ni usos. | Selección absoluta explícita de autoridad, cuarentena y consumos; identidad del ejecutor fijada y comprobada. |
| Bloqueante | SYSTEM, usuario y Administradores tienen FullControl sobre autoridad. Borrar un consumo permite replay dentro de la ventana. | Política de confianza y protección del registro frente a borrado/rollback, separada del proceso web; validación de esa protección. |
| Bloqueante | La barrera está fijada a false en configuración; el comando de activación/retirada solo permite aislamiento de pruebas. | Diseñar, autorizar y probar el ciclo real de mantenimiento y recuperación antes de desplegarlo. |
| Bloqueante | Barrera cooperativa: SQL externo, operaciones masivas y escrituras ajenas al modelo pueden eludirla. | Ventana con exclusión operativa de todos los escritores de itm, no solo de documentos. |
| Pendiente | El inventario archivado no tiene el formato de ejecución del motor actual. | Manifiesto nuevo, revisado durante mantenimiento, con bytes y SHA-256 exactos aprobados. |
| Pendiente | Los 109 PDF originales siguen en el árbol público. | Mantener su conservación; planificar el cierre de publicación y la activación del flujo en alcance separado. |

No se corrigieron estos puntos en producción: esta fase solo autoriza
auditoría, diseño y pruebas aisladas. No se deja un permiso listo para ejecutar.

## Implementación revisada

Se revisaron los comandos TransicionarDocumentosInscripcion y BloqueoDocumental;
los servicios TransicionDocumental, ControlTransicionDocumental,
AutorizacionTransicionDocumental, EntornoTransicionReal,
DocumentosInscripcion e HistorialDocumentosInscripcion; las dos configuraciones
documentales; informes 2G-2B-1, 1.1 y 1.4 y pruebas correspondientes.
También se inspeccionaron rutas, controladores, modelos, migraciones,
middleware documental, consola, scripts locales y configuración Apache activa.

Referencias principales del código actual:

- EntornoTransicionReal::autoridad(), preparar(), verificarEscritores(), verificarAcl().
- AutorizacionTransicionDocumental::validar(), usar(), auditoria(), ventana().
- ControlTransicionDocumental::operacion(), exclusivaReal(), congelar(), retirar().
- TransicionDocumental::planificar(), ejecutarReal(), procesar(), copiar().
- HistorialDocumentosInscripcion::incorporarInicialPrivada().

No se ejecutó el comando real, ni siquiera para intentar superar una
precondición. El plan actual se calculó únicamente en memoria, dentro de una
transacción de lectura. No se guardó un manifiesto ejecutable de documentos reales.

## Identidades y permisos necesarios

| Rol | Estado observado | Recomendación |
| --- | --- | --- |
| Ejecutor Artisan | Usuario interactivo autorizado, propietario de las raíces; SID comprobada localmente y omitida aquí por privacidad. Token fuera del sandbox con Administradores efectivo. | Usar esa misma identidad, binario PHP explícito y directorio ITM; no sustituirla por SYSTEM. La operación documental no debe depender de privilegios administrativos adicionales. |
| Apache/PHP | Padre 23672 e hijo 33140, propietarios SID S-1-5-18, servicio LocalSystem. | Solo permisos que requiera la aplicación; no facultades de firma ni administración de consumos. Revisar la separación del servicio web en una fase autorizada. |
| Administrador de confianza/verificador | Todavía no se ha provisionado una autoridad real. | Controlar la instalación de la clave pública y aprobar su huella por canal independiente; el ejecutor solo debe leerla. |
| Firmante | No existe una identidad o clave real preparada por estas fases. | Operador separado, con clave privada fuera del servidor de ejecución y del repositorio, preferentemente en equipo/dispositivo independiente. |

Artisan se inspeccionó desde C:/wamp64/www/ITM con
C:/wamp64/bin/php/php8.4.15/php.exe, PHP CLI 8.4.15, entorno local.
No se confía en el primer php del PATH. La conexión efectiva leída es
MySQL 8.4.7, itm, 127.0.0.1:3306; se comprobó también la huella del UUID.
Hay otro listener MySQL en 3307: no se consultó ni se utilizó para la transición.

El usuario puede leer los originales —se calcularon sus 109 hashes— y las ACL
le conceden acceso a documentos privados y bloqueos. La escritura efectiva
en esas raíces se probó en 1.4, no se repitió sobre raíces reales en esta fase.
is_writable observado aquí es solo un indicio, no una prueba de creación.
Crear consumos reales **no está preparado**: autoridad/usos no existe.

Permisos mínimos propuestos: lectura de originales y artefactos aprobados;
creación/escritura de destinos y temporales propios; creación de bloqueos;
creación duradera de consumos sin facultad de borrar consumos anteriores;
lectura de clave pública; privilegios SQL limitados a la operación necesaria.
La inspección de escritores necesita visibilidad suficiente de procesos SQL;
si se separa del ejecutor debe existir una atestación verificable y fresca.
No se confunden los permisos del firmante, del verificador y del proceso web.

## Resolución de rutas

Para no publicar el nombre del perfil, **U** representa la raíz absoluta del
usuario comprobada localmente, redactada como
`%LOCALAPPDATA%` (por ejemplo, `C:/Users/<usuario>/AppData/Local`). **U** es
solo una abreviatura de este informe, no una variable que deba expandirse al
ejecutar ni un valor literal para instalar.

| Raíz | Artisan actual | Apache/PHP y discrepancia |
| --- | --- | --- |
| Documentos | U/ITM-PrivateStorage/inscripciones; ruta absoluta en .env | La selección absoluta se verificó en 1.4; .env y configuración siguen intactos. No depende de su LOCALAPPDATA. |
| Bloqueos | U/ITM-TransitionLocks; ruta absoluta en .env | Misma selección absoluta, ya comprobada en 1.4. |
| Autoridad | U/ITM-DocumentAuthorization, derivada por autoridad() | Derivaría de C:/Windows/system32/config/systemprofile/AppData/Local/ITM-DocumentAuthorization. No se debe utilizar ese contexto. |
| Consumos | U/ITM-DocumentAuthorization/usos; inexistente | Derivaría de la autoridad del perfil de sistema, también equivocada para este plan. |
| Cuarentena | La preparada es U/ITM-PrivateQuarantine; no hay opción efectiva de disco para ella. preparar() exige el hermano de la autoridad del ejecutor. | Si se intentase el canal real bajo ese perfil, su expectativa también cambiaría. PHP web no está autorizado como ejecutor. |

La diferencia de LOCALAPPDATA se comprobó mediante lectura de memoria del
padre y del hijo Apache, sin escribir en procesos, sin habilitar privilegios
de depuración y sin publicar diagnósticos web. Ambos contienen exactamente
`C:\Windows\system32\config\systemprofile\AppData\Local`; la huella coincide
con esa cadena conocida. El valor del usuario es distinto. La consulta del
registro de carpetas de SYSTEM no coincidió con el entorno vivo y no se tomó
como fuente autoritativa para el proceso.

La fase 1.4 confirmó el funcionamiento del disco y bloqueo con ambas
identidades; eso no demuestra que autoridad() resuelva al mismo lugar. Aquí
se comprobó la discrepancia sin ejecutar un arranque HTTP de diagnóstico.

**Propuesta de diseño, sin instalar:** conservar los destinos absolutos del
usuario ya verificados y elegir explícitamente los otros tres destinos de la
tabla. Introducir configuración local no versionada para autoridad, cuarentena
y registro de consumos, sin fallback implícito a LOCALAPPDATA en el canal real.
Los nombres de esas nuevas opciones deben implementarse y probarse en una
fase posterior: hoy no existen. No inventar parámetros CLI para utilizarlas.
Fijar la SID ejecutora y las rutas canónicas en el contexto firmado; rechazar
SID, raíz o caché diferentes antes de consumir. Canonizar barras y comparación
de Windows una sola vez antes de firmar; solicitud/contexto se comparan de forma
estricta y una variación de representación también puede invalidarlos.

Mantener estos valores personales solo en configuración y artefactos privados.
No cambiar LOCALAPPDATA globalmente ni crear una segunda autoridad para SYSTEM.
Si se elige otra raíz, su creación, ACL y pruebas requieren autorización nueva.

## Ed25519, confianza y uso único

| Control implementado | Resultado de revisión |
| --- | --- |
| Firma | sodium_crypto_sign_verify_detached sobre los bytes exactos del payload decodificado; Base64 estricto y longitudes de clave/firma comprobadas. |
| Huellas | SHA-256 del sobre y del archivo de clave pública exigidas; inventario, respaldo y auditoría también ligados por hashes. La huella corresponde al archivo de clave, incluida su representación Base64. |
| Objetivo | formato=1, acción exacta, ID hexadecimal de 32 caracteres; contexto y solicitud iguales a los esperados. Incluye host, puerto, base, versión/UUID, proyecto, raíces, lote y datos originales. |
| Tiempo | Ventana confirmada, enteros, máximo 1.800 segundos, desde inclusivo/hasta exclusivo. Auditoría de antigüedad máxima 300 segundos al validar/consumir. |
| Rutas | Material fuera del repositorio y publicaciones declaradas; límites de lectura, validación absoluta, rechazo de enlaces/ancestros redirigidos. Se revisan ACL en el canal real. |
| Consumo | Apertura exclusiva xb de id.consumida; fflush y fsync si disponible; un competidor no puede crear el mismo nombre. |
| Interrupción | El consumo no se elimina al fallar. Incluso un archivo de consumo vacío bloquea el reuso. Un fallo posterior requiere ID y permiso nuevos. |
| Error | El comando imprime códigos permitidos o TRANSICION_DETENIDA; no mensajes SQL, rutas o trazas arbitrarias. Esto no equivale a una auditoría completa de toda la configuración de logs. |

usar() vuelve a comprobar clave, artefactos y auditoría antes de consumir.
exigirActiva() vuelve a exigir la ventana durante el procesamiento. Se
revalida el contexto bajo exclusión antes del consumo. La firma acredita
lo aprobado por el firmante: no demuestra por sí misma que se restauró un
respaldo o que la lista de procesos/publicaciones estaba completa.
El respaldo SQL solo recibe controles estructurales mínimos —contenido no
vacío, USE itm y terminación de dump— y hashes; no se restaura automáticamente.

**Límite confirmado por prueba aislada:** si un principal borra id.consumida,
el mismo sobre vuelve a validarse y consumirse mientras su ventana y auditoría
sigan vigentes. No se ha encontrado un bypass de la firma Ed25519; es una
dependencia del registro persistente. Las ACL actuales permiten borrar al
usuario, a SYSTEM y a Administradores. Un Apache comprometido bajo SYSTEM
tiene, por tanto, demasiado poder sobre ese registro futuro.

La comprobación verificarAcl() admite únicamente esos principals y revisa
propietario, pero no exige derechos mínimos, un registro inmutable o ausencia
de DELETE/DELETE_CHILD. No se debe presentar como protección antirreplay frente
a un administrador local. Las comprobaciones realpath tampoco constituyen
confinamiento frente a un principal privilegiado que sustituya rutas entre
comprobaciones y operaciones.

Diseño propuesto:

- Clave privada exclusivamente en el firmante externo; nunca en este host,
  argumentos CLI, repositorio, respaldo de la aplicación o proceso Apache.
- Clave pública administrada por el responsable de confianza, solo lectura
  para el ejecutor; huella aprobada por canal independiente.
- Sobre firmado e inventario como artefactos privados inmutables durante la
  ventana, legibles por el ejecutor y sin acceso HTTP.
- Consumos separados de la administración de claves. Proteger creación y
  persistencia con una política validada de no borrado/rollback y registro
  externo duradero. Para resistir a SYSTEM/administradores, usar una frontera
  independiente del mismo host; una ACL local no basta para esa amenaza.
- Actualizar el verificador y su prueba de política sin ampliar principals
  por comodidad. Una separación con otros propietarios/SID requerirá revisar
  la lista fija actual. Probar escritura autorizada y rechazo de borrado bajo
  las identidades exactas antes de emitir un permiso real.

No se generó, instaló, firmó o consumió material real. Las claves y firmas de
la suite son únicamente ficticias y temporales.

## Inventario y respaldos

Estado de lectura actual: 8 inscripciones, 6 referencias no vacías, 109 PDF,
5.132.534 bytes, 6 asociaciones exactas, 103 sin asociación y cero contenidos
repetidos. planificar() comprobó rutas, originales, referencias compartidas,
metadatos e integridad estructural de la tabla documental. No faltan originales
ni existen asociaciones ambiguas; no se asignaron propietarios por nombres.
documentos_inscripcion está vacía.

Los SHA-256 de los originales y las asociaciones coinciden con los dos
inventarios archivados. Huella del inventario histórico común:
`1592f563a0add3643a8b145fe2901361bfe213597b5167776523386e723ce283`.
También coinciden los hashes SQL/PDF de la comprobación final de 1.4.

El plan actual calculado en memoria tiene huella
`3e23b41e4195a3eefd07e93c9a95f5b689272843c8fe28ac40195f563fb93819`.
Es evidencia de esta auditoría, **no aprobación para ejecutarlo**. El inventario
archivado utiliza files/summary; el motor exige formato/referencias/archivos.
No pasar el archivo histórico directamente como --inventario de ejecución.

Respaldos relevantes bajo la raíz privada ITM-PrivateBackups:

| Respaldo | Integridad y cobertura | Limitaciones |
| --- | --- | --- |
| itm-documental-fase2d-20261019-074951-827c21f2 | Todos los hashes SHA256.json coinciden. Las 109 copias documents/public coinciden en bytes y hashes. SQL legible, terminación completa, 28 CREATE TABLE. | Dump del 19/10, 07:50 aproximadamente, anterior a la tabla documental; restore_test_executed=false. |
| itm-documental-fase2f-20261019-082341-e06f4f94 | Todos los hashes SHA256.json coinciden; inventario y originales coincidentes; SQL legible con 28 tablas. | No contiene una nueva carpeta documents: la cobertura documental comprobada es la de 2D. Dump de 08:23 aproximadamente; restore_test_executed=false. |

Huella SQL 2D:
`41e27552db082d6939c2678c471ac2efe16f3df0ceae5d67dcff90520dc15c49`.
Huella SQL 2F:
`8bff4769172dac6dab8d635b86738a07ba3655f194ee770ae9043236ff80ec98`.

Ambos están fuera de DocumentRoot/Alias. Se revisaron ACL y reparse points
del respaldo documental 2D y las raíces privadas: sin accesos Allow ajenos
al usuario, SYSTEM y Administradores, ni enlaces peligrosos. El padre de
respaldos hereda una ACL restringida; las raíces documentales tienen DACL
protegida. No se confundió esa diferencia con exposición pública.

La antigüedad de horas no los invalida como evidencia, pero no cubren el
esquema actual de 29 tablas ni acreditan una restauración. **Es obligatorio
un respaldo actualizado y restauración aislada futura**, con conteos, esquema,
restricciones y hashes comprobados. Conservar estos anteriores. No se creó
ningún respaldo nuevo de datos reales ni se ejecutó SQL de respaldo.

## Concurrencia y exclusión

| Camino | Cobertura actual |
| --- | --- |
| Tres POST de inscripción/subida/envío y GET de generación PDF | Middleware ProtegerEscriturasDocumentales mantiene operacion() en la petición; incluye generación, escritura y compensación documental. |
| Servicios DocumentosInscripcion y HistorialDocumentosInscripcion | Envueltos en operacion(); incorporación inicial exige exclusión. |
| Inscripcion::save/delete y actualizaciones de instancias | Sobrescrituras del modelo participan en la barrera; también afectan aprobación, rechazo, reactivación y tareas que usan esas instancias. |
| DocumentoInscripcion directo, Query Builder, actualizaciones masivas, SQL directo | No quedan bloqueados automáticamente por el save/delete de Inscripcion. La prueba nueva confirma el bypass de Query Builder con barrera activa. |
| Otras tablas, sesiones y caché de aplicación | No están globalmente inmovilizadas por la barrera documental; pueden invalidar el hash del conjunto de 28 tablas. |
| Scripts locales, copias del proyecto y herramientas de administración | Pueden abrir conexiones y eludirla. test_debug.php crea datos mediante factories si alguien lo ejecuta; no se ejecutó. backup.bat es una herramienta de dump, no una exclusión de escritores. |
| Jobs/scheduler/servicios externos | Deben inventariarse y drenarse por separado. No se observó app/Jobs ni tareas de scheduler en routes/console.php; no demuestra inexistencia de automatismos externos. |

Las lecturas inicial y final observaron cero conexiones SQL ajenas no daemon,
cero transacciones ajenas, cero jobs, eventos habilitados, triggers y routines
de itm. La inspección de tareas Windows no encontró acciones relevantes.
No se detectaron workers Artisan/PHP o clientes SQL de los tipos revisados en
esa instantánea. Apache y MySQL siguieron activos. Terminales, herramientas
Wamp y otros proyectos pueden iniciar conexiones nuevas en cualquier momento.

verificarEscritores() y las atestaciones firmadas son fotografías, no reservas
duraderas del servidor. flock solo coordina quienes abren el mismo archivo.
La validación del contexto cubre las 28 tablas originales antes de consumir;
no bloquea sus escritores durante todo el procesamiento. Debe verificarse
también su integridad al final de la ventana.

Procedimiento propuesto: aislar acceso a itm en todos los contextos web,
administrativos y de consola; impedir nuevas conexiones de escritura salvo la
del ejecutor y el observador autorizado; detener/drenar workers y tareas,
cerrar herramientas manuales y comprobar transacciones activas. Mantener ese
control durante respaldo, firma, copia y validación final. Evitar también
sesiones/cachés de páginas que escriban en las tablas originales. La forma
concreta de aislamiento —cuentas SQL, controles de acceso o mantenimiento—
debe aprobarse y ensayarse; no se promete exclusión total por detener solo
itm.test o por activar el fichero bloqueado. No se aplicó ninguna de esas
medidas ni se bloquearon usuarios en esta fase.

## ACL y exposición HTTP

Las cuatro raíces reales siguen vacías, sin cambios de ACL, propietario o
contenido. usos sigue inexistente. SYSTEM conserva FullControl; es apropiado
revisar qué derechos necesita para documentos y bloqueos, pero no heredar esa
misma política para firma/consumos. No se cambiaron permisos.

La inspección actual de 6.735 directorios publicados —www y destinos de los
Alias— no siguió reparse points y encontró solo public/storage, dirigido al
almacenamiento público conocido. Las raíces privadas y respaldos no son
destinos de DocumentRoot, Alias o enlaces publicados.

21 comprobaciones HTTP HEAD, sin cookies, proxy o redirecciones: 4 recursos
estáticos respondieron 200 y 17 rutas protegidas respondieron 403. Se usaron
itm.test, localhost, IP y Host alternativo. Quedan denegados .env, rutas
internas, herramientas Wamp bajo itm.test, /ITM y el ZIP por accesos alternativos.
No se crearon marcadores reales ni diagnósticos web. No se solicitaron PDF
reales ni páginas dinámicas que pudieran escribir sesiones MySQL.

Esto acredita las rutas/configuración revisadas, no privacidad de los PDF
que siguen en public/storage ni una auditoría del contenido de ITM - copia.
Localhost permanece predeterminado. No hubo cambios de VirtualHost, hosts,
.htaccess o cachés, ni recargas/reinicios de servicios.

## Recuperación e interrupciones

| Momento | Estado previsto y recuperación propuesta |
| --- | --- |
| Antes del consumo | Validación de lectura; si falla, no debe gastarse permiso. Puede existir un lock de mantenimiento si ya se adquirió. Revisar estado antes de reintentar. |
| Tras crear consumo, antes de escribirlo | El nombre ya reserva el ID; incluso vacío se rechaza. No borrarlo para reusar. Requiere revisión y permiso nuevo. |
| Tras consumir, antes de copiar | Puede quedar marcador bloqueado. El permiso se conserva consumido aun si no se registraron versiones. |
| Durante copia o cuarentena | Originales intactos; pueden quedar copias completas y .part aleatorios. No limpiar globalmente. Revisar hashes y nombres exactos; nuevo permiso para reanudar. |
| Después de comprobar SHA-256 | El motor publica con enlace duro exclusivo, sin sobreescribir un destino distinto. Copias idénticas pueden reutilizarse. Probar acceso efectivo a enlaces duros en las raíces reales en fase autorizada; aquí solo hay pruebas temporales. |
| Durante creación de versiones | Una transacción exterior agrupa las seis versiones; un fallo intermedio debe revertir todas, conservando archivos. Se comprueba con SQLite; no se afirma ensayo de caída real de MySQL. |
| Después del commit y antes de completado.json | Puede haber seis versiones correctas sin marcador de éxito. No inferir rollback: comprobar SQL, copias y manifiesto. El motor admite versiones iniciales idénticas sin duplicarlas. |
| Tras completar | El bloqueo permanece. La habilitación del flujo, retirada de barrera y retirada de exposición pública son decisiones posteriores, no automáticas. |

No hay una transacción única entre NTFS, consumos y MySQL. fflush/fsync no
equivalen a una garantía completa frente a corte de energía y pérdida de
metadatos del directorio; se necesita preservación duradera del registro y
reconciliación tras una caída. Un .part residual no se elimina automáticamente
en toda excepción. Una copia completa y su temporal podrían compartir inode
por el enlace duro: no editar ninguno durante recuperación.

La reanudación requiere ID nuevo y auditoría/respaldo revisados, mismo lote y
plan solo si siguen coincidiendo, referencias intactas y contenido compatible.
Para corrupción, destino distinto o cambio de inventario: detenerse y obtener
un nuevo diseño/autorización. No asociar archivos ni descartar versiones para
forzar un reintento. Una persistencia parcial del lote no es el resultado
normal de la transacción; exige investigar intervención externa o caída.

El motor verifica cada destino al copiar; su comprobación posterior del plan
revisa originales y destinos asociados, no una nueva pasada integral de las
103 copias de cuarentena. La validación posterior independiente de las 109
copias es obligatoria antes de declarar éxito operativo.

No restaurar MySQL completo mientras existan escritores ni como compensación
automática. Una restauración o borrado selectivo necesita autorización propia.
Nunca restaurar el registro de consumos a una versión anterior ni borrar IDs
consumidos. La retirada de bloqueo real todavía no tiene un canal operativo
autorizado: debe resolverse antes de ejecutar para evitar quedar sin salida.

## Procedimiento futuro propuesto — no ejecutado

1. Autorizar primero la preparación de los bloqueantes: configuración absoluta
   de autoridad/cuarentena/consumos, identidad, política de confianza y ciclo real
   de barrera/recuperación. Mantener los valores funcionales false por defecto.
2. Revisar y probar esos cambios en SQLite/temporales, incluidos no borrado de
   consumos, caída, reanudación, SID inesperada y rutas/cachés divergentes.
3. Con autorización de infraestructura separada, provisionar autoridad pública
   y consumos con permisos mínimos, comprobar NTFS/espacio/acceso con ficticios
   y verificar ausencia de publicación. No alojar la clave privada en este host.
4. Aprobar ejecutor —usuario interactivo verificado—, PHP 8.4.15 por ruta completa,
   directorio ITM, destinos absolutos y formato canónico. Registrar su SID y la
   identidad MySQL/UUID en el contexto privado que aprobará el firmante.
5. Reservar mantenimiento y establecer una exclusión verificable de escritores
   de todas las tablas de itm, incluyendo otros proyectos y herramientas Wamp.
   Drenar actividad antes de fijar cualquier estado firmado.
6. Crear un respaldo actualizado de las 29 tablas y de los 109 originales;
   verificar hashes, cobertura y restauración en instancia desechable, nunca
   sobre itm real. Conservar respaldos anteriores y proteger el registro de usos.
7. Generar el manifiesto actual del motor en privado, revisar 8/6/109/103 y cero
   ambigüedades; aprobar sus bytes exactos y SHA-256, sin inferir asociaciones.
8. Mediante el mecanismo de mantenimiento que todavía debe implementarse y
   aprobarse, habilitar y conservar una única barrera compartida por todos los
   ejecutores. Confirmar contexto, opciones restantes false y plan de salida.
9. Completar una auditoría fresca de procesos, tareas, publicaciones y hashes de
   toda la configuración HTTP. Registrar restauración verificada sobre la huella
   exacta del SQL y la correspondencia con los datos originales.
10. En el firmante externo, generar/provisionar clave solo con autorización
    específica y aprobar independientemente la huella pública. Emitir un único
    permiso de ID nuevo, vinculado a entorno, SID, lote, manifiesto, SQL, auditoría
    y ventana de máximo 30 minutos. La auditoría tendrá menos de cinco minutos.
11. Solicitar autorización expresa de ejecución sobre esos artefactos concretos,
    indicando qué se copiará, qué seis versiones se crearán, que 103 archivos irán
    a cuarentena y que originales/barrera se conservarán. No reutilizar esta
    auditoría ni una aprobación conversacional genérica como permiso firmado.
12. Solo entonces usar el canal --ejecutar --real con confirmación exacta
    INCORPORAR_DOCUMENTOS_REALES y opciones existentes --inventario, --sha256,
    --cuarentena, --lote, --autorizacion, --autorizacion-sha256 y --clave-sha256.
    No existe --force ni un atajo para las precondiciones. Aquí no se prepara
    una línea completa con valores reales ni un permiso utilizable.
13. Comprobar antes de liberar mantenimiento: seis versiones iniciales correctas,
    109 destinos y originales con hashes aprobados, 103 sin asociación en
    cuarentena, referencias y 28 tablas originales intactas, consumo duradero,
    manifiestos consistentes y ausencia de exposición de las raíces externas.
    Para activar flujo/historial, retirar barrera o cerrar acceso a originales,
    obtener el alcance explícito correspondiente y verificar descargas autorizadas.

Detenerse por cualquier discrepancia de identidad, configuración/caché, ruta,
ACL, publicación, hash, asociación, respaldo/restauración, escritor, reloj,
ventana, clave, firma, consumo, espacio, bloqueo o plan de recuperación.
No intentar reparar esos casos durante una ejecución sin nueva autorización.

## Pruebas y estado final

Comando de pruebas utilizado, con PHP explícito y Xdebug desactivado solo para
ese proceso:

```text
php -d xdebug.mode=off vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter "AlmacenamientoDocumentalExternoTest|AutorizacionTransicionDocumentalTest|DocumentosInscripcionTest|HistorialDocumentosInscripcionTest|TransicionDocumentalTest" --do-not-cache-result
```

Resultado: **117 pruebas, 1.007 aserciones, cero fallos, errores u omisiones**.
Las cuatro comprobaciones nuevas cubren consumo vacío tras interrupción,
vencimiento entre validación/consumo, replay al borrar un consumo ficticio y
bypass de Query Builder. Las dos últimas caracterizan riesgos existentes:
que pasen no significa que esos riesgos estén corregidos.
Las suites anteriores cubren firmas inválidas, huellas, lote, entornos,
artefactos alterados, concurrencia, copia parcial, rollback, reanudación y
conservación de los seis asociados y 103 ficticios sin propietario.

SQLite :memory: y PDO SQLite se verifican antes de migraciones de prueba;
storage y raíces documentales se fuerzan a TEMP por el bootstrap existente.
No se ejecutó Composer test ni comandos que limpien cachés globales.
Los dos archivos de pruebas cambiados pasan php -l. Se retiraron 74 raíces
temporales nuevas de la suite, cotejadas contra el inventario previo, con
rutas absolutas verificadas bajo TEMP y sin seguir enlaces. Los temporales
anteriores se conservaron.

Verificación real inicial/final mediante SELECT, SHOW y transacciones
READ ONLY terminadas con rollback:

- 29 tablas, mismos conteos y hashes de filas; mismos hashes de SHOW CREATE TABLE.
- 8 inscripciones y 6 referencias, sin cambios; documentos_inscripcion vacía.
- 109 originales, mismos bytes y SHA-256 del inventario archivado y de 1.4.
- 103 PDF sin asociación verificada, sin traslado ni asociación nueva.
- Raíces privadas reales vacías y consumos inexistentes.
- enabled, history_enabled, legacy_read_enabled y transition_guard_enabled false;
  sin marcador bloqueado, claves, permisos o autorizaciones reales nuevas.
- .env, configuraciones, cachés packages/services, siete archivos Apache/hosts
  y ACL comprobadas sin cambios. Caché real config.php sigue ausente.
- .env conserva SHA-256 `A97AEC4EDFD553A0C8F99D3C7482B1C35F6D48AA991556FA982AB89F549DA8FD`.
- Apache y MySQL Running; itm.test y localhost continúan con estáticos 200.

Cambios persistentes de esta fase: este informe y cuatro métodos nuevos en
tests/Feature/AutorizacionTransicionDocumentalTest.php y
tests/Feature/TransicionDocumentalTest.php. No se modificó código de aplicación,
configuración real o infraestructura. Los auxiliares e inventarios de hashes
temporales se retiraron; no se crearon respaldos de datos reales, rutas web,
claves reales o permisos firmados. Las solicitudes de comprobación pueden
añadir entradas normales a logs HTTP; no se borraron logs.

No hubo operaciones Git, worktrees, restauraciones reales, cambios de ACL,
bloqueos de usuarios, reinicios, transición o ejecución de scripts heredados.
Se detiene el trabajo y se espera nueva autorización.

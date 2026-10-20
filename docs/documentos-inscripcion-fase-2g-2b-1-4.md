# Fase 2G-2B-1.4 — configuración y verificación del almacenamiento externo

Fecha: 19 de octubre de 2026, America/El_Salvador.
Fase autorizada completada; sin transición documental real.

## Resultado

Laravel reconoce las raíces externas de documentos y bloqueos. Artisan y
Apache/PHP pueden crear, leer, actualizar, comprobar SHA-256, renombrar y
eliminar archivos ficticios en las cuatro raíces preparadas. También se
comprobó exclusión mediante flock sobre un archivo ficticio de bloqueos.
La configuración cacheada funciona en ambos procesos mediante una caché
privada aislada. La caché real de configuración sigue ausente.

Resultado automatizado final: **113 pruebas, 983 aserciones, cero fallos,
cero errores y cero omisiones**. MySQL y los 109 PDF originales permanecen
intactos. Las cuatro opciones documentales siguen desactivadas.

## Inspección y configuración

Se leyeron completos filesystems.php, documentos_inscripcion.php,
DocumentosInscripcion, EntornoTransicionReal, ControlTransicionDocumental,
TransicionDocumental y los informes de las fases 1.1 y 1.3. Se revisaron además
el bootstrap y proveedor de Laravel, los comandos de caché del framework y
las suites documentales. No se asumieron nombres de discos o variables.

| Parámetro | Antes | Después |
| --- | --- | --- |
| `INSCRIPCION_DOCUMENTOS_ROOT` | Ausente; fallback `storage/app/private/inscripciones` | `%LOCALAPPDATA%/ITM-PrivateStorage/inscripciones` del usuario autorizado |
| `INSCRIPCION_TRANSICION_CONTROL_ROOT` | Ausente; fallback `storage/framework/transicion-documental` | `%LOCALAPPDATA%/ITM-TransitionLocks` del usuario autorizado |
| Disco `inscripciones` | local, private, serve=false, throw=true | Sin cambios salvo la raíz |
| Otros discos y enlaces Laravel | Configuración original | Sin cambios; hash de la configuración efectiva coincidente |

En .env se añadieron exclusivamente las dos entradas de rutas y un comentario.
Los valores instalados son rutas absolutas con barras `/`, elegidas desde el
LOCALAPPDATA del usuario interactivo. La notación `%LOCALAPPDATA%` de este
informe es una redacción: no se depende de expandir esa variable bajo SYSTEM.
Se comprobó byte por byte que todo el contenido anterior de .env se conserva
como prefijo. No se reproduce su contenido ni sus secretos.

Los archivos config/filesystems.php y config/documentos_inscripcion.php ya
soportaban estas variables y no se modificaron. No se agregaron rutas
personales fijas al código versionado ni se seleccionaron nuevas raíces para
cuarentena o autoridad: esas carpetas se verificaron usando sus ubicaciones
preparadas, sin cambiar el contrato de la transición real.

Estado efectivo antes y después:

| Opción | Valor |
| --- | --- |
| `documentos_inscripcion.enabled` | false |
| `documentos_inscripcion.legacy_read_enabled` | false |
| `documentos_inscripcion.history_enabled` | false |
| `documentos_inscripcion.transition_guard_enabled` | false |

Seleccionar rutas no activa el flujo privado, historial, lectura heredada,
barrera, autorización criptográfica ni transición.

## Raíces, ACL y publicación

Se comprobaron bajo el LOCALAPPDATA del usuario autorizado:

- ITM-PrivateStorage y su hijo inscripciones.
- ITM-PrivateQuarantine.
- ITM-DocumentAuthorization.
- ITM-TransitionLocks.

Las raíces y sus ancestros no son symlinks ni junctions. Las DACL originales
son protegidas, con FullControl exclusivamente para el usuario autorizado,
SYSTEM y Administradores. No se modificó ninguna ACL, propietario o permiso.
Las SDDL antes/después coinciden. Al finalizar, inscripciones, cuarentena,
autoridad y bloqueos vuelven a estar vacíos; el padre PrivateStorage solo
contiene inscripciones. No existen claves, usos, autorizaciones, marcadores
de barrera, documentos privados reales o cuarentena documental nueva.

Se revisaron los DocumentRoot y Alias activos del principal, vhosts,
autoindex y los tres Alias de Wamp. Ninguno publica las raíces externas.
Una inspección de 6.726 directorios de www y los destinos de los Alias,
sin seguir reparse points, encontró únicamente el junction conocido
ITM/public/storage hacia ITM/storage/app/public. No hay enlaces publicados
hacia las carpetas privadas. El VirtualHost y sus protecciones no cambiaron.

## Respaldos

Directorio privado nuevo, fuera de www y del repositorio:

`%LOCALAPPDATA%/ITM-PrivateBackups/fase-2g-2b-1-4-e7764fdefaba4190a16fa77d44760add`

Hereda la ACL privada ya existente del padre: usuario autorizado, SYSTEM y
Administradores. No fue necesario modificar permisos. Se respaldaron .env,
las dos configuraciones documentales, los archivos de caché packages/services,
los siete archivos Apache/hosts de referencia y, antes de editarlos, los
cuatro archivos de pruebas afectados. Se verificaron los SHA-256 de las copias.
No se copiaron PDF ni se exportaron datos MySQL.

manifest.json registra los archivos iniciales, hashes y SDDL. Los archivos
test-bootstrap-before.json, storage-test-before.json y tests-after.json
complementan las evidencias de las pruebas. Los inventarios SQL/PDF contienen
conteos y hashes, no filas SQL ni contenidos documentales. Las evidencias
http-isolation.json, apache-normal.json, apache-cached.json, artisan-cached.json,
phpunit-first.xml y phpunit.xml conservan los resultados de esta fase.
Los scripts temporales de comprobación quedaron archivados solo en esa
carpeta privada, sin ruta web ni token utilizable.

SHA-256 de .env:

- Anterior y respaldo: `9AF9CC30FAA8CEDAC2B23A8F44E37EA0350A55AB4EB2EEF3AFEFBE67D88BB79F`.
- Final: `A97AEC4EDFD553A0C8F99D3C7482B1C35F6D48AA991556FA982AB89F549DA8FD`.

## Identidad y pruebas efectivas

Se utilizó PHP 8.4.15 de Wamp. El servicio declara LocalSystem y PHP ejecutado
mediante Apache confirmó **LocalSystem, SID S-1-5-18**, con whoami ejecutado
desde el propio proceso PHP. La SAPI fue apache2handler. Artisan ejecutó con
la SID del usuario interactivo autorizado, distinta de SYSTEM.

El diagnóstico temporal se publicó con nombre aleatorio, restringió REMOTE_ADDR
a loopback, exigió GET y un token enviado por encabezado cuyo hash se verificó
con hash_equals, y devolvió únicamente resultados sanitizados. Sin token
respondió 404. No expuso rutas privadas, credenciales ni el nombre del usuario.
Se retiró el archivo público y el material temporal del token al terminar.

Se arrancó el contenedor de Laravel mediante el kernel de consola, sin ejecutar
el kernel HTTP, sesiones, vistas o modelos. Las pruebas de almacenamiento no
abrieron conexiones MySQL; después del arranque se seleccionó SQLite en memoria
como defensa adicional del diagnóstico. No se usaron credenciales de alumnos.

| Contexto | Configuración normal | Caché aislada |
| --- | --- | --- |
| Comando Artisan temporal | Correcto | Correcto |
| Apache/PHP autenticado y loopback | Correcto | Correcto |

En cada contexto se verificaron las cuatro raíces con nombres únicos:
creación exclusiva, lectura, escritura adicional, SHA-256, renombrado sin
sobrescritura y eliminación. Sobre bloqueos se comprobó LOCK_EX y el rechazo
de un segundo bloqueo concurrente, sin crear transicion.lock ni bloqueado.
Además se comprobó el adaptador Storage::disk('inscripciones') con
put/get/hash/move/delete y su coincidencia con la raíz efectiva. Todos los
archivos ficticios se eliminaron mediante limpieza incluso ante errores.
No se llamó a guardar() ni a la transición sobre documentos reales.

## Configuración cacheada aislada

Antes de actuar se comprobó que bootstrap/cache/config.php no existía. Se
leyó la implementación de config:cache y config:clear: ambos utilizan la ruta
efectiva APP_CONFIG_CACHE. Se ejecutó config:cache únicamente con esa variable
de proceso apuntando a un archivo exclusivo dentro del respaldo privado.
No se ejecutó config:clear sobre la ruta real ni se reconstruyó su caché.

El primer intento con una ruta `C:\...` fue rechazado por la normalización de
Laravel, que la interpretó como relativa a base_path. No llegó a escribir una
caché. Se utilizó la forma absoluta desde la raíz de la unidad `/Users/...`,
reconocida por esta implementación, y se comprobó físicamente que el archivo
generado estaba en el directorio privado esperado. La segunda ejecución fue
correcta. El error inicial pudo añadir una entrada al log normal de Laravel;
no se eliminaron logs existentes ni se incluyeron sus contenidos en el informe.

Artisan y Apache confirmaron configurationIsCached=true, las raíces correctas,
operaciones ficticias satisfactorias y todas las opciones desactivadas. La
selección de caché de Apache solo existió dentro de la solicitud autenticada.
El archivo de caché aislada se eliminó después de guardar su SHA-256; la
variable de proceso se retiró. packages.php y services.php conservaron hashes.
La configuración real permanece sin cachear.

## Aislamiento HTTP

Se crearon cuatro marcadores ficticios únicos, uno por raíz, y se enviaron
64 solicitudes GET sin cookies, sin credenciales de aplicación, sin seguir
redirecciones y sin proxy, usando resolución explícita hacia loopback.

| Hosts | Variantes por raíz |
| --- | --- |
| itm.test, localhost, 127.0.0.1, otro-host.test | Nombre de carpeta directo, /storage, /ITM y /icons |

Resultado: **20 respuestas 403 y 44 respuestas 404**. Ningún cuerpo contenía
el marcador. Todos los marcadores y cuerpos temporales fueron eliminados.
Un intento inicial falló al componer la URL en PowerShell; su único marcador
se retiró mediante finally antes de repetir correctamente las 64 solicitudes.

Los marcadores utilizaron extensión .conf, bloqueada bajo itm.test, para evitar
que una URL inexistente ejecutara Laravel y escribiera sesiones MySQL. Estas
pruebas verifican las variantes indicadas; la ausencia de publicación general
se sustenta también en la revisión de DocumentRoot, Alias y enlaces. No se
presentan como una prueba exhaustiva de cualquier URL o cambio futuro.

Después de retirar el diagnóstico, itm.test/images/logo_itm.png y
localhost/favicon.ico siguieron respondiendo 200. No se solicitaron PDF reales
ni rutas dinámicas de login o formularios. No se cambió Apache, hosts o .htaccess
y no hubo recargas ni reinicios en esta fase. El servicio sigue Running con
PID padre 23672.

## Pruebas automatizadas y correcciones

Se detectó que seleccionar rutas reales en .env podía hacer que el bootstrap
heredara la raíz real de bloqueos durante las pruebas. Antes de ejecutar la
suite se reforzó tests/documentos-isolated-bootstrap.php para fijar la raíz
documental al storage temporal, dejar el control vacío para usar el fallback
temporal de cada aplicación y forzar las tres variables funcionales a false.
El bootstrap existente mantiene APP_ENV=testing, SQLite :memory:, DB_URL vacío
y la comprobación del PDO SQLite antes de las migraciones de prueba.

Se añadió una regresión para exigir raíces temporales y opciones desactivadas.
El primer pase obtuvo 108/113: un test intentaba sobrescribir variables
externas mediante el repositorio inmutable de Dotenv y dos limpiezas usaban
unlink sobre enlaces de directorio de Windows; esos residuos afectaban además
dos inventarios de prueba. Se corrigieron únicamente las pruebas: modificación
y restauración explícita del entorno ficticio, y rmdir para enlaces de
directorio en Windows. No se alteró el comportamiento de la aplicación.

Comando de la repetición satisfactoria:

```text
php vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter "AlmacenamientoDocumentalExternoTest|AutorizacionTransicionDocumentalTest|DocumentosInscripcionTest|HistorialDocumentosInscripcionTest|TransicionDocumentalTest" --do-not-cache-result --log-junit <respaldo-privado>/phpunit.xml
```

Resultado final: 113/113, 983 aserciones, sin errores, fallos u omisiones.
No se ejecutó Composer test, porque incluye config:clear global. Las pruebas
de transición y criptografía actuaron exclusivamente sobre SQLite y artefactos
ficticios; no constituyen ejecución ni autorización de la transición real.
Se verificó sintaxis PHP de los cuatro archivos de pruebas modificados.

Se retiraron 140 directorios temporales nuevos de estos pases, identificados
contra el inventario previo y patrones aleatorios exactos de la suite. Se
comprobaron rutas absolutas bajo TEMP y la limpieza no siguió reparse points.
Se conservaron todos los temporales preexistentes.

## Integridad final

Inventarios inicial y final mediante transacciones MySQL READ ONLY, SELECT
y rollback; sin exportar filas ni ejecutar DML, migraciones o seeders reales:

- 29 tablas presentes: las 28 originales y documentos_inscripcion.
- Conteo y hash determinista de filas idénticos en las 29 tablas.
- 8 inscripciones y 6 referencias documentales, sin cambios.
- documentos_inscripcion permanece vacía.
- 109 PDF, mismas rutas y SHA-256; no se movieron, copiaron, modificaron o borraron.
- Raíces privadas, cuarentena, autoridad y bloqueos vacíos al terminar.
- Opciones y barrera desactivadas; no se crearon claves ni permisos reales.
- Configuración Apache, hosts, discos restantes y ACL conservados.

La comparación se repitió después de terminar la suite y retirar sus
temporales: cero diferencias de tablas y PDF.

## Archivos modificados

1. .env: solo las dos rutas externas y comentario, fuera de cualquier operación Git.
2. tests/documentos-isolated-bootstrap.php: aislamiento del entorno documental.
3. tests/Feature/AlmacenamientoDocumentalExternoTest.php: regresión y manejo de variables ficticias.
4. tests/Feature/DocumentosInscripcionTest.php: limpieza de enlaces de directorio en Windows.
5. tests/Feature/TransicionDocumentalTest.php: misma corrección de limpieza.
6. Este informe.

Los scripts y configuración auxiliares de .phase14 y el diagnóstico temporal
de public se retiraron. Solo permanecen respaldos y evidencias privados fuera del repositorio.
Los logs operativos pueden haber añadido las solicitudes y el error de caché
indicados. No se realizaron operaciones Git ni se crearon worktrees.

## Riesgos pendientes y siguiente fase recomendada

Los PDF históricos siguen en storage/app/public y public/storage conserva su
junction; esta fase no los privatiza. ITM - copia sigue fuera del alcance de
una auditoría de contenido, aunque sus enlaces se incluyeron en la inspección
de árboles publicados. No se validaron sesiones, cookies, APP_URL, flujos
dinámicos o accesos desde máquinas remotas.

EntornoTransicionReal::autoridad() deriva su raíz de getenv('LOCALAPPDATA').
Ese valor coincide con la carpeta preparada bajo Artisan del usuario, pero
no bajo Apache/SYSTEM. La prueba de acceso de Apache a la carpeta autorizada
fue satisfactoria; no implica que su autoridad() resuelva esa carpeta. No se
cambió este contrato ni se creó una autoridad para SYSTEM. Una futura fase
debe definir explícitamente la identidad del ejecutor, preferentemente el
Artisan del usuario autorizado, y resolver esta diferencia si pretende otro
contexto. Este punto no habilita cambios de ACL o criptografía.

Se recomienda una siguiente fase de revisión del ejecutor, inventario,
respaldos, escritores concurrentes, mantenimiento y autorización antes de
cualquier transición. El alcance de esa fase debe aprobarse por separado.

## Reversión limitada

Antes de revertir, comparar los hashes actuales con las evidencias finales
para no sobrescribir trabajo posterior. Verificar las copias privadas y
restaurar únicamente .env y los cuatro archivos de pruebas modificados.
La restauración de .env debe devolver el SHA-256 anterior indicado; los hashes
de pruebas están en tests-after.json. Conservar las ACL originales.

Si existen ediciones posteriores, retirar solo las dos entradas de rutas y el
comentario de esta fase y aplicar inversamente los cambios concretos de las
pruebas, conservando el resto. No restaurar archivos Apache/hosts de referencia,
no tocar MySQL, PDF, logs ni carpetas privadas. La caché real ya estaba y sigue
ausente: no se necesita config:clear, config:cache ni reiniciar servicios.
La reversión recuperaría los fallbacks anteriores de las raíces documentales.

Fase terminada. Se espera autorización para cualquier trabajo posterior.

# Fase 2G-2B-1.1 — almacenamiento externo y ACL

Revisión local del 19 de octubre de 2026. Esta fase no habilita la transición.

## Publicación efectiva revisada

El servicio `wampapache64` utiliza Apache 2.4.65, `-k runservice`, cuenta
`LocalSystem`. La consulta directa de propietario/argumentos de sus procesos
fue denegada por Windows; la identidad de servicio se comprobó en CIM.
La consulta de configuración `httpd -n wampapache64 -t -D DUMP_VHOSTS
-D DUMP_RUN_CFG -D DUMP_INCLUDES` identificó:

- `conf/httpd.conf`, `conf/extra/httpd-autoindex.conf`,
  `conf/extra/httpd-vhosts.conf` y los tres archivos de `alias` de Wamp.
- Un único VirtualHost `localhost:80`, DocumentRoot `${INSTALL_DIR}/www`.
- `Indexes`, `Includes`, `FollowSymLinks`, `MultiViews`, `AllowOverride All`,
  `Require local`. `Require local` no autentica usuarios de Laravel.
- Alias de icons, adminer, phpmyadmin5.2.3, phpsysinfo y ScriptAlias cgi-bin;
  sus destinos están dentro de Wamp, no en las nuevas raíces externas.
- PHP 8.4.15 como módulo de Apache. Artisan y el servidor Laravel de puerto
  8000 usan el mismo usuario interactivo, comprobado mediante CIM.
- No se encontraron tareas programadas relevantes mediante consulta elevada.

Esto comprueba la configuración del servicio en disco; no permite asegurar
que ningún archivo haya cambiado desde el arranque. No se reinició el servicio.
El junction `public/storage` sigue apuntando a `storage/app/public` y expone
los documentos públicos por diseño. Bajo Apache, el repositorio y su carpeta
`storage/app/private` están dentro del DocumentRoot: llamarla privada en
Laravel no constituye protección frente al servidor HTTP.
No se solicitaron documentos personales para comprobar ese riesgo.

Debe autorizarse por separado un VirtualHost de ITM con DocumentRoot en
`ITM/public`, sin listado de directorios ni publicación del repositorio,
y revisar cualquier otro acceso al árbol anterior. Esta fase no cambia Apache.

## Directorios preparados

Bajo `%LOCALAPPDATA%`, fuera del repositorio, DocumentRoot y Alias revisados:

| Directorio | Finalidad futura |
| --- | --- |
| `ITM-PrivateStorage` | Padre exclusivo protegido |
| `ITM-PrivateStorage/inscripciones` | Documentos privados |
| `ITM-PrivateQuarantine` | Documentos sin asociación comprobada |
| `ITM-DocumentAuthorization` | Autoridad, todavía vacía |
| `ITM-TransitionLocks` | Bloqueo cooperativo, todavía vacío |

Se conservó el nombre `ITM-DocumentAuthorization` que ya exige
`EntornoTransicionReal`; no fue necesario modificar ese componente.
No se creó `usos`, claves, autorizaciones, marcadores ni cuarentena documental.
Antes de crear se verificaron inexistencia, ruta absoluta y ausencia de
reparse points en todos los ancestros. No se tocaron respaldos.

DACL protegida, sin herencia: usuario actual, SYSTEM y Administradores,
FullControl con herencia a hijos. El usuario es propietario y ejecuta Artisan;
SYSTEM corresponde a la identidad configurada del servicio Apache. No se
añadieron grupos amplios. No se cambió ninguna ACL preexistente.
Las SDDL originales se registraron **antes** de cada cambio en un informe
privado `%TEMP%/itm-private-storage-phase211-acl.json`.

Se probaron creación exclusiva, lectura, escritura, SHA-256, renombrado sin
sobrescritura y eliminación de archivos ficticios en cada directorio.
Esto demuestra acceso efectivo para Artisan/servidor Laravel con la misma SID.
No se ejecutó una prueba de escritura bajo SYSTEM: permanece pendiente si se
pretende operar desde Apache, porque no se publicó un script ni alteró servicios.

## Comprobaciones HTTP

Archivos ficticios con nombres aleatorios en las cinco carpetas: 30 solicitudes
sin cookies ni seguimiento de redirecciones, usando el servidor habitual 8000,
localhost, rutas storage, el Alias icons y traversal representativo. Resultados
por carpeta: 403, 404, 404, 403, 404 y 400. Ninguna respuesta incluyó el marcador
ficticio. Las respuestas temporales y archivos ficticios fueron eliminados.
Una primera prueba se interrumpió por composición incorrecta de URL; se retiró
su único archivo tras verificar nombre/contenido exactos y se repitió completa.

Los resultados son pruebas representativas, no una garantía de ausencia de
cualquier publicación futura. Los demás Alias se revisaron por configuración.
No se probó ningún PDF real ni se cambió la configuración HTTP.

## Configuración preparada, no activada

Dos opciones sin rutas personales fijas:

- `INSCRIPCION_DOCUMENTOS_ROOT`: raíz opcional del disco inscripciones.
  Sin ella conserva el comportamiento anterior. Continúa local, privado y
  `serve=false`, sin URL pública.
- `INSCRIPCION_TRANSICION_CONTROL_ROOT`: raíz opcional del bloqueo.
  Sin ella conserva el fallback existente; no habilita la barrera.

No se cambió `.env`, no se reconstruyó caché y ninguna opción documental fue
activada. Por tanto **Laravel todavía no utiliza las nuevas carpetas**.
Las variables solo eligen ubicaciones: no constituyen autorización de ejecución.
El canal real sigue exigiendo manifiesto firmado, inventario, respaldo, ventana
y demás precondiciones. Los destinos externos también deben quedar fuera de
todas las publicaciones HTTP declaradas en la autorización futura.

## Pruebas y límites

`AlmacenamientoDocumentalExternoTest` utiliza SQLite en memoria y raíces
temporales ficticias. Cubre configuración externa sin activación, raíz HTTP
rechazada sin crearla, traversal/rutas relativas/ADS/NUL, persistencia externa,
junction real de prueba en Windows y rechazo de la DACL temporal heredada amplia.
La preparación de una DACL desde un subproceso PHPUnit falló por privilegios
WRITE_DAC; no se debilitó la comprobación del servicio. Se sustituyó esa
preparación por la prueba de lectura de ACL amplia. Las cinco DACL restringidas
se comprobaron separadamente: herencia deshabilitada, tres identidades
permitidas, ningún archivo residual. Queda limitada la prueba automática positiva
de ACL al entorno que permita modificar DACL de sus carpetas temporales.
Las suites existentes cubren simulación sin efectos, aislamiento de MySQL,
autorizaciones, concurrencia, historial y conservación de documentos.

No se ha ejecutado una transición real. Antes de la siguiente fase se requiere
autorizar la selección efectiva de raíces, resolver la publicación del
repositorio, revisar las ACL bajo la identidad que ejecutará la transición y
repetir inventario, respaldo, revisión de escritores y mantenimiento. Ningún
archivo de autorización real se debe crear como consecuencia de estas pruebas.

Comprobación de integridad antes y después: 28 tablas originales sin cambios
de filas, 8 inscripciones, 6 referencias, tabla documental vacía y 109 PDF con
los SHA-256 originales. Opciones y barrera desactivadas, respaldo sin cambios.

Resultado final: 246 pruebas aprobadas, 1.723 aserciones, sin omisiones, con
`tests/documentos-isolated-bootstrap.php`, SQLite `:memory:` y almacenamiento
temporal. Incluye las seis pruebas nuevas y regresión de autorizaciones,
transición, documentos, historial, reintentos, solicitudes, encargados, cupos,
cursos próximos y aprobación/caché de rutas. Sintaxis PHP verificada en los
dos archivos de configuración y la prueba nueva. La comparación SHA-256 del
código previo solo detectó los dos cambios de configuración y los dos archivos
nuevos de esta fase; `.env` y los demás cambios locales se conservaron.

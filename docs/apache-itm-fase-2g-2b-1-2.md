# Fase 2G-2B-1.2 — propuesta de VirtualHost, no aplicada

Inspección del 19 de octubre de 2026. Solo se crearon este informe y
`apache-itm-vhost.example.conf` dentro de `docs/`. No se instaló configuración,
no se modificó hosts ni se reiniciaron servicios.

## Apache identificado y límites de la comprobación

Servicio Windows `wampapache64`, Running, LocalSystem, PID 23672;
proceso hijo 23928. ImagePath del servicio:
`C:/wamp64/bin/apache/apache2.4.65/bin/httpd.exe -k runservice`.
El ejecutable del servicio devuelve Apache/2.4.65 (Win64), Apache Lounge VS17,
compilado el 24 de julio de 2025. No se confunde con el servidor Artisan:
este último atiende `127.0.0.1:8000`, proceso PHP 32692.

La consulta con `-n wampapache64 -t -D DUMP_INCLUDES -D DUMP_VHOSTS
-D DUMP_RUN_CFG` identificó:

| Configuración | Archivo |
| --- | --- |
| Principal | `C:/wamp64/bin/apache/apache2.4.65/conf/httpd.conf` |
| Índices y Alias icons | `conf/extra/httpd-autoindex.conf` |
| VirtualHosts | `conf/extra/httpd-vhosts.conf` |
| Alias adicionales | `C:/wamp64/alias/adminer.conf`, `phpmyadmin5.2.3.conf`, `phpsysinfo.conf` |

Las rutas relativas de la tabla son respecto al directorio de Apache indicado.
Se comprobó la configuración actualmente seleccionada en disco por el servicio.
Apache no permite reconstruir con estas consultas una copia exacta de la
configuración que permanece en memoria desde su arranque: no se afirma que
nadie hubiera editado los archivos antes de esta inspección.

Listen configurado: `0.0.0.0:80` y `[::0]:80`, confirmado por sockets del PID
23672. No se observó listener 443. ServerName principal `localhost:80`.
Único VirtualHost: `_default_:80`, ServerName y ServerAlias `localhost`,
DocumentRoot `C:/wamp64/www`. No existe todavía un VirtualHost de ITM.
Hosts solo contiene las entradas de localhost IPv4/IPv6; no existe `itm.test`.

`<Directory />` deniega acceso por defecto, pero los bloques de `www` permiten
acceso local, AllowOverride All y opciones amplias. El VirtualHost habilita
Indexes, Includes, FollowSymLinks y MultiViews. La protección global `.ht*`
no protege `.env`, copias ZIP o los demás directorios del repositorio.
`Require local` restringe el origen de la conexión; no autentica usuarios ITM.

Módulos comprobados con `-M`: rewrite, negotiation, dir, authz_core,
authz_host, alias, log_config, mime y PHP, entre otros. PHP se carga como
módulo 8.4.15, no se propone cambiarlo ni cambiar el puerto.

## Dependencias y riesgos

En `www` existen introPHP, ITM, ITM - copia, practica, products-api, tarea,
Toyota y recursos wamplangues/wampthemes. Su presencia implica dependencias
potenciales del servidor predeterminado; no demuestra que todos estén en uso.
No se inspeccionaron sus datos ni historiales de navegación. El inicio de Wamp
y sus herramientas comparten localhost.

Alias globales: `/icons/` al directorio icons de Apache; `/adminer`,
`/phpmyadmin5.2.3`, `/phpsysinfo` a sus aplicaciones de Wamp; ScriptAlias
`/cgi-bin/` al directorio cgi-bin de Apache. Ninguno apunta a las nuevas raíces
privadas externas. Los Alias globales pueden heredarse por un nuevo VirtualHost;
la propuesta deniega esas URL solo bajo itm.test, manteniéndolas en localhost.

Los riesgos actuales incluyen:

- Publicación del repositorio vía localhost/ITM, con potencial acceso a `.env`,
  vendor, storage, bootstrap/cache, database, app y tests. No se solicitaron
  esos archivos ni se descargaron datos personales para demostrar el riesgo.
- `ITM.zip` presente en la raíz HTTP. Solo se comprobó su nombre; no se abrió
  ni se afirma que contenga un respaldo concreto. La propuesta bloquea ese
  archivo de forma específica, sin modificarlo ni eliminarlo.
- `ITM - copia` es otro árbol publicado: no se revisó su contenido ni se asume
  que esté libre de documentos o credenciales. Requiere revisión separada.
- `public/storage` es un junction a `storage/app/public`. Cambiar DocumentRoot
  no privatiza los 109 PDF originales. No se retiró el enlace ni se probaron
  documentos reales por HTTP.
- Un nuevo VirtualHost, por sí solo, dejaría disponible localhost/ITM. Por eso
  el ejemplo incluye una guardia global limitada al árbol ITM y una excepción
  pública exclusivamente dentro del VirtualHost itm.test. No basta con añadir
  únicamente el bloque VirtualHost.

## Propuesta preparada

El ejemplo usa `itm.test`, nombre de desarrollo reservado que no requiere
registrar un dominio público. No contiene credenciales ni rutas privadas
personales. DocumentRoot es exclusivamente `C:/wamp64/www/ITM/public`.

Mantiene localhost como primer servidor y no redefine sus Alias. Su guardia
del árbol ITM cerraría deliberadamente el acceso anterior `/ITM/...` por
localhost/IP/otros hosts. Antes de aplicarlo se debe aceptar este cambio de URL;
no se propone bloquear `www` ni alterar las demás aplicaciones.

El bloque público autoriza solo conexiones locales. Options FollowSymLinks
deshabilita los índices, SSI, CGI y MultiViews heredados, conservando el enlace
storage. AllowOverride FileInfo Options=Indexes,MultiViews cubre las reglas
actuales de `public/.htaccess`: encabezados de autorización, redirecciones,
front controller y desactivación de índices/MultiViews. No se concede All.
Los FilesMatch y LocationMatch añaden defensas frente a nombres internos y
herramientas heredadas; no reemplazan la autorización de Laravel.

Los directorios privados externos y respaldos fuera de www no reciben Alias
ni nuevas autorizaciones Directory. Deben seguir fuera de cualquier publicación
HTTP y conservar sus ACL. Las futuras rutas documentales requerirán autorización
por documento. No se activan opciones ni se seleccionan raíces privadas aquí.

## Validación realizada sin instalar

Desde PowerShell, comandos ejecutados, sin `-k` ni sustitución temporal:

```powershell
$apache = 'C:/wamp64/bin/apache/apache2.4.65/bin/httpd.exe'
& $apache -n wampapache64 -t
& $apache -n wampapache64 -M
& $apache -n wampapache64 -t -c 'Include C:/wamp64/www/ITM/docs/apache-itm-vhost.example.conf'
& $apache -n wampapache64 -t -D DUMP_VHOSTS -c 'Include C:/wamp64/www/ITM/docs/apache-itm-vhost.example.conf'
```

El `-c` agrega el ejemplo únicamente al proceso de análisis sintáctico, después
de leer la configuración habitual; no lo instala en el servicio en ejecución.
Resultado final: Syntax OK, localhost permanece predeterminado y se agrega
un solo namevhost itm.test:80. No hay ServerName duplicado en la configuración
revisada. Compartir el listener 80 mediante nombres es intencional: el ejemplo
no añade Listen ni intenta abrir otro puerto. Los módulos necesarios existen.
Los logs propuestos son separados, bajo logs de Wamp; no se crearon al validar.

Estas pruebas no ejecutan las reglas para solicitudes ni procesan automáticamente
el `.htaccess` como lo haría una petición. Permanecen pendientes las pruebas HTTP
del nuevo hostname, autorización Directory efectiva, junction storage, rewrites,
cookies, redirecciones y URL generadas por Laravel tras una aplicación autorizada.
No se presenta Syntax OK como certificación funcional de seguridad.

HTTP actual de solo lectura, sin cookies, sin redirecciones y descartando cuerpo:
localhost/favicon.ico, localhost/ITM/public/images/logo_itm.png y
127.0.0.1:8000/images/logo_itm.png respondieron 200. Solo se utilizaron recursos
estáticos institucionales, sin crear archivos ni consultar PDF. No se probaron
formularios, login ni páginas que pudieran escribir sesiones en MySQL.

## Procedimiento futuro: requiere nueva autorización

1. Acordar ventana y comprobar nuevamente servicio, includes, Alias, hosts,
   rutas y hashes. Registrar respuestas estáticas y accesos relevantes de otros
   proyectos sin ejecutar operaciones con efectos de escritura.
2. Respaldar `httpd-vhosts.conf` y hosts antes de editarlos, fuera de www y del
   repositorio, por ejemplo bajo `%LOCALAPPDATA%/ITM-PrivateBackups` en un
   directorio nuevo fechado con sufijo aleatorio. Guardar bytes originales,
   ACL y SHA-256; verificar lectura, integridad y que no se sobreescriba nada.
   No se creó ese respaldo en esta fase.
3. Copiar el contenido revisado del ejemplo al final de `httpd-vhosts.conf`,
   fuera del VirtualHost localhost, sin cambiar el bloque existente. Es un
   fragmento: no sustituir el archivo completo por el ejemplo. No incluirlo
   permanentemente desde docs, porque esa carpeta forma parte del repositorio.
4. Añadir a hosts `127.0.0.1 itm.test` y `::1 itm.test`, sin borrar entradas.
   Revisar antes conflictos de nombre con VPN/proxy, hosts u otra configuración.
5. Ejecutar `-n wampapache64 -t` y `-t -D DUMP_VHOSTS` ya sobre los archivos
   candidatos instalados. Exigir Syntax OK y localhost predeterminado. Si falla,
   restaurar los archivos sin reiniciar. Revisar permisos para los nuevos logs.
6. Solo con autorización para ello, recargar/reiniciar Apache mediante Wamp.
   Confirmar arranque y revisar errores de configuración sin divulgar secretos.
7. Comprobar localhost, recursos Wamp y proyectos que realmente se utilicen.
   Sus rutas deben conservarse; localhost/ITM pasa a estar bloqueado expresamente.
8. Comprobar itm.test con estáticos y después las rutas Laravel aprobadas para
   prueba. Una prueba inicial con `curl --resolve itm.test:80:127.0.0.1` evita
   depender de DNS. Probar permisos de alumno/admin sin ejecutar POST reales.
   Comprobar APP_URL efectivo, cookies y assets: no cambiar `.env` ni caché sin
   autorización adicional. Iniciar sesión en otro hostname puede crear sesión.
9. Verificar 403/404 para `.env`, vendor, app, tests, database, bootstrap/cache,
   respaldos y Alias bloqueados. No mostrar cuerpos de respuestas sensibles.
   Comprobar también el acceso alternativo por localhost, IP y hosts ajenos.
   Usar artefactos ficticios autorizados para probar lectura de contenido.
10. Confirmar que storage conserva el comportamiento requerido y documentar que
    sus PDF continúan públicos hasta la transición separada. No descargar PDF
    reales como prueba. Revisar que ningún nuevo Alias exponga raíces externas.
11. Volver a comparar configuración, estado documental y hashes. Esta aplicación
    no autoriza cambios de MySQL, traslado de PDF ni activación del flujo privado.

## Reversión futura

Si todavía no se recargó el servicio, restaurar los archivos verificados evita
cambiar su comportamiento en memoria. Si ya se aplicó, restaurar exactamente
vhosts y hosts respaldados, comprobar sus SHA-256 y ACL, validar sintaxis y
recargar Apache bajo la misma autorización de mantenimiento. Confirmar localhost
y los otros proyectos. No borrar logs, documentos, datos o directorios privados.
La reversión restauraría también la exposición anterior del árbol ITM: no se
debe presentar como una solución permanente de seguridad.

## Resultado y siguiente fase

Solo documentación y ejemplo: no cambios de aplicación, Apache, hosts o MySQL;
sin nuevas claves, autorizaciones, opciones ni barreras. La comprobación de
lectura verificó 28 tablas originales intactas, documentos_inscripcion vacía,
8 inscripciones, 6 referencias y SHA-256 de los 109 PDF originales conservados.
No hubo copias de PDF ni transición. No se necesitan pruebas de base de datos
para estos dos archivos de documentación; no se ejecutó PHPUnit contra MySQL.
Al terminar, los SHA-256 de los siete archivos revisados (principal, vhosts,
autoindex, tres Alias y hosts) coincidieron con los iniciales; Apache conservó
sus PID 23672 y 23928. La consulta final sin el ejemplo siguió mostrando solo
localhost y Syntax OK. Los logs propuestos no existen: no fueron abiertos por
la validación. La barrera documental y el modo mantenimiento siguen ausentes.

La siguiente fase debe autorizar expresamente respaldo, edición de vhosts y
hosts, recarga del servicio y comprobaciones funcionales. Antes de una transición
documental seguirá pendiente seleccionar raíces externas en Laravel y comprobar
escritura bajo la identidad que vaya a ejecutar la operación. Esta propuesta
no activa ninguna de esas acciones.

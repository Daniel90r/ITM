# Fase 2G-2B-1.3 — VirtualHost aplicado y validado

Fecha: 19 de octubre de 2026, contexto horario America/El_Salvador.
Este informe sustituye el resultado anterior de fase detenida por permisos.

## Resultado y alcance

Aplicación controlada completada: itm.test tiene DocumentRoot exclusivamente
C:/wamp64/www/ITM/public. Localhost conserva su bloque original y es el
VirtualHost predeterminado. Se añadió únicamente 127.0.0.1 itm.test a hosts;
la resolución del sistema devuelve esa dirección y la petición sin --resolve
al logotipo responde 200. No se añadió IPv6.

Se leyeron íntegramente apache-itm-vhost.example.conf y el informe 1.2.
La configuración inicial coincidía con la descrita: Apache 2.4.65,
servicio wampapache64, localhost como único VirtualHost, listeners 80,
includes principal/autoindex/vhosts y los tres Alias Wamp previstos.
No existía itm.test ni una entrada hosts previa en conflicto.

## Permisos efectivos

El proceso aislado mostró High Mandatory Level, pero Administradores solo
para denegar y net session devolvió acceso denegado. La comprobación fuera
del sandbox, aprobada por el mecanismo de ejecución, confirmó Administradores
habilitado, WindowsPrincipal.IsInRole(Administrator)=True y net session sin
errores («No hay entradas en la lista»). Las operaciones administrativas se
realizaron por esa vía. No se cambiaron ACL de Apache ni hosts, no se tomó
posesión ni se usaron otras cuentas. No hubo rechazo del revisor automático.

## Respaldos privados

Directorio nuevo y exclusivo, fuera de www y del repositorio:

`C:\Users\<usuario-autorizado>\AppData\Local\ITM-PrivateBackups\<directorio-de-respaldo-de-la-fase>`

La ruta se presenta anonimizada; los marcadores no son valores literales de configuración.

Se guardaron los bytes originales de los siete archivos de configuración
revisados, incluidos los dos modificados. Cada copia fue leída y su SHA-256
comparado con el original antes de editar; la comprobación final volvió a
confirmar los siete respaldos. manifest.json conserva rutas, hashes y SDDL.
La carpeta tiene herencia deshabilitada y acceso completo solo para el usuario
actual, SYSTEM y Administradores; los archivos heredan esa protección.
Las ACL de los siete originales permanecen iguales a las iniciales.
No se copiaron PDF ni datos MySQL.

Evidencias privadas adicionales: baseline-http.json, service-before.json,
service-after.json, syntax-installed.txt, vhosts-installed.txt, http-after.json,
integrity-after.json y pdf-before.json. Este último contiene únicamente rutas
y hashes de los PDF, sin copiar su contenido.

## Cambios instalados

- httpd-vhosts.conf: se conservaron los bytes originales como prefijo y se
  agregó una copia independiente del fragmento revisado, fuera del bloque
  localhost. Solo se ajustó el comentario inicial para indicar su aplicación.
  No se dejó un Include permanente hacia docs.
- Guardia global Directory para C:/wamp64/www/ITM: Require all denied,
  AllowOverride None y Options None. Bloqueo específico de ITM.zip en www.
- VirtualHost itm.test con Directory público Require local,
  Options FollowSymLinks y AllowOverride FileInfo Options=Indexes,MultiViews.
  Se conservan las reescrituras del .htaccess público existente.
- Bloqueos del ejemplo para Alias Wamp, nombres internos, archivos ocultos y
  extensiones de configuración, logs y respaldos. Logs separados en
  C:/wamp64/logs/itm-error.log e itm-access.log, creados por Apache.
- hosts: adición de la entrada IPv4 autorizada sin eliminar las existentes.

No se modificaron httpd.conf, autoindex, Alias compartidos, .env, código,
configuración Laravel, cachés ni opciones documentales.

## Sintaxis, recarga e incidencia resuelta

Se ejecutó con el binario del servicio:

```powershell
httpd.exe -n wampapache64 -t
httpd.exe -n wampapache64 -t -D DUMP_VHOSTS
httpd.exe -n wampapache64 -k restart
```

Validación instalada y validación final: Syntax OK, código de salida 0.
DUMP_VHOSTS muestra localhost predeterminado y exactamente un itm.test:80.
La recarga controlada mantuvo el padre PID 23672 y creó el hijo PID 33140.
El servicio permanece Running. El log registra recepción de restart,
«resuming normal operations» y arranque de los worker threads del nuevo hijo.
Los errores de autorización observados corresponden a las pruebas 403 esperadas.

Incidencia: el primer intento interpretó el mensaje Syntax OK de stderr como
una excepción por ErrorActionPreference=Stop de Windows PowerShell. Antes de
cualquier recarga se restauraron exclusivamente vhosts y hosts desde sus
respaldos y se confirmó que los siete hashes originales coincidían.
Se ajustó el tratamiento de stderr y se reaplicó; se exigieron tanto código 0
como Syntax OK antes de recargar. No fue un error sintáctico de Apache.
No se necesitó reversión después de la recarga satisfactoria.

## Pruebas HTTP

Solicitudes HEAD con curl --noproxy '*', sin credenciales, sin cookies,
sin seguir redirecciones y descartando cuerpos. Para nombres de prueba se usó
--resolve con 127.0.0.1. No se descargaron archivos sensibles ni PDF reales.
La línea base dio 200 para favicon de localhost, CSS de practica y Toyota y
/ITM/public/images/logo_itm.png. Esta última ruta alternativa pasa a 403 por diseño.

| Host | Ruta | Estado final |
| --- | --- | --- |
| itm.test | `/images/logo_itm.png` | 200 |
| itm.test | `/.env` | 403 |
| itm.test | `/.git/config` | 403 |
| itm.test | `/vendor/` | 403 |
| itm.test | `/app/` | 403 |
| itm.test | `/tests/` | 403 |
| itm.test | `/database/` | 403 |
| itm.test | `/bootstrap/cache/` | 403 |
| itm.test | `/backups/` | 403 |
| itm.test | `/backup/` | 403 |
| itm.test | `/icons/` | 403 |
| itm.test | `/adminer` | 403 |
| itm.test | `/phpmyadmin5.2.3` | 403 |
| itm.test | `/phpsysinfo` | 403 |
| itm.test | `/cgi-bin/` | 403 |
| itm.test | `/ITM.zip` | 403 |
| localhost | `/favicon.ico` | 200 |
| localhost | `/ITM/` | 403 |
| localhost | `/ITM/public/images/logo_itm.png` | 403 |
| localhost | `/ITM/.env` | 403 |
| localhost | `/ITM/test_debug.php` | 403 |
| localhost | `/ITM/crear_admin.php` | 403 |
| localhost | `/ITM/storage/` | 403 |
| localhost | `/ITM/docs/apache-itm-vhost.example.conf` | 403 |
| localhost | `/ITM.zip` | 403 |
| 127.0.0.1 | `/favicon.ico` | 200 |
| 127.0.0.1 | `/ITM/` | 403 |
| 127.0.0.1 | `/ITM/public/images/logo_itm.png` | 403 |
| 127.0.0.1 | `/ITM/.env` | 403 |
| 127.0.0.1 | `/ITM/test_debug.php` | 403 |
| 127.0.0.1 | `/ITM/crear_admin.php` | 403 |
| 127.0.0.1 | `/ITM/storage/` | 403 |
| 127.0.0.1 | `/ITM/docs/apache-itm-vhost.example.conf` | 403 |
| 127.0.0.1 | `/ITM.zip` | 403 |
| otro-host.test | `/favicon.ico` | 200 |
| otro-host.test | `/ITM/` | 403 |
| otro-host.test | `/ITM/public/images/logo_itm.png` | 403 |
| otro-host.test | `/ITM/.env` | 403 |
| otro-host.test | `/ITM/test_debug.php` | 403 |
| otro-host.test | `/ITM/crear_admin.php` | 403 |
| otro-host.test | `/ITM/storage/` | 403 |
| otro-host.test | `/ITM/docs/apache-itm-vhost.example.conf` | 403 |
| otro-host.test | `/ITM.zip` | 403 |
| localhost | `/practica/css/stylesheet1.css` | 200 |
| localhost | `/Toyota/css/style.css` | 200 |
| localhost | `/tarea/Archivo.js` | 200 |
| itm.test, resolución hosts normal | `/images/logo_itm.png` | 200 |

La conservación de localhost también se comprobó con IP y un Host desconocido:
el favicon sigue respondiendo 200, mientras que el árbol ITM y el ZIP están
bloqueados. Los Alias Wamp se deniegan exclusivamente dentro de itm.test;
sus archivos y el bloque localhost no cambiaron.

Las herramientas PHP heredadas de la raíz del repositorio quedan fuera del
DocumentRoot. La única entrada PHP encontrada bajo public es index.php.
Se comprobó explícitamente la denegación de /ITM/test_debug.php por localhost,
IP y Host alternativo; también de /ITM/crear_admin.php como nombre de prueba.
No se ejecutaron las herramientas ni se solicitaron rutas PHP inexistentes
bajo itm.test que pudieran entrar a Laravel y escribir sesiones.

Los recursos estáticos de practica y Toyota mantienen 200 antes/después;
tarea responde 200 después. introPHP y products-api conservan sus rutas y
configuración compartida, pero no se ejecutaron sus entradas dinámicas: no se
afirma validación funcional completa de todos los proyectos. Tampoco se
probaron login, sesiones, cookies, redirecciones o generación de URL de Laravel;
APP_URL no se cambió. Estos límites evitan efectos de escritura en MySQL.

## Integridad

Los SHA-256 finales son:

| Archivo | Antes y respaldo | Después |
| --- | --- | --- |
| httpd.conf | `395BF5919C10147DC21590435F2FC8238A265B821C6114B7CEF484EE01BE204C` | `395BF5919C10147DC21590435F2FC8238A265B821C6114B7CEF484EE01BE204C` |
| httpd-vhosts.conf | `0231B957030AFBD7C1E04CDABC41F27796607D91F5B858F0D38095150CEF1763` | `2D5F4258D20CBCDC118159AB88E5E65D0BD36E5CB99C91C2DFB41B2B4231E1B2` |
| httpd-autoindex.conf | `2C2EAAC987460E013FEE8DCE912E5D6EC41CF537EB09CDBB3FE92C23B77FC04C` | `2C2EAAC987460E013FEE8DCE912E5D6EC41CF537EB09CDBB3FE92C23B77FC04C` |
| adminer.conf | `CD01F773856A704074068BDF72DF1F4661383C24229B64A0FE9AB73FCC42FBCB` | `CD01F773856A704074068BDF72DF1F4661383C24229B64A0FE9AB73FCC42FBCB` |
| phpmyadmin5.2.3.conf | `BC6845C09C909DB23BAF7AC9AFB5F44AA4A71F7871ED28AF24AF33399CE450A6` | `BC6845C09C909DB23BAF7AC9AFB5F44AA4A71F7871ED28AF24AF33399CE450A6` |
| phpsysinfo.conf | `3123D47A41E3DD3AA7510304F9228BE2D6805F243377EA5A37E68CCBAEA15CA9` | `3123D47A41E3DD3AA7510304F9228BE2D6805F243377EA5A37E68CCBAEA15CA9` |
| hosts | `74857FDF6D38CB4DBB1B8C913483B7381F3ED0EB5400F756A0998B73C82AC84F` | `88B2F2CEE6B4133C49E75DD1F2DF85D98112A4296E7069B0A552BB537E4912E7` |

Solo difieren vhosts y hosts. Todas las ACL originales siguen iguales.
La comparación de los 109 PDF originales antes/después por ruta y SHA-256
produjo cero diferencias: no hubo traslado, eliminación ni copias.
La primera lectura de esa comparación envolvió el array JSON como un único
objeto en PowerShell; se corrigió el desempaquetado y se compararon los 109
objetos reales. No se trató como cambio documental.

No se accedió a MySQL con clientes ni se ejecutaron migraciones, seeders,
PHPUnit, Artisan, formularios, login o transición documental. No se realizaron
operaciones Git. No se activaron opciones documentales ni se modificaron
barreras, autorizaciones o raíces privadas de Laravel.

## Riesgos y límites conservados

public/storage sigue siendo el junction hacia storage/app/public. Los 109 PDF
históricos continúan en ese árbol público: este VirtualHost no los privatiza.
No se probaron esos documentos por HTTP. No se agregó ningún Alias a respaldos
o raíces privadas. ITM - copia sigue fuera de esta intervención y no fue
auditado; no se garantiza la privacidad de esa copia independiente.
Require local limita conexiones a origen local; no sustituye autenticación.
No se verificó acceso desde una máquina remota.

## Reversión limitada a esta fase

Si posteriormente se autoriza revertir: comprobar primero hashes actuales y
que no existan cambios ajenos posteriores; verificar los SHA-256 de las copias
contra manifest.json; restaurar únicamente httpd-vhosts.conf y hosts desde
este directorio privado, conservando las ACL originales; exigir hashes
originales y Syntax OK y recargar con -n wampapache64 -k restart. Confirmar
localhost y recursos de los otros proyectos. No restaurar archivos de datos,
no borrar logs ni tocar PDF. Revertir reabriría las rutas inseguras anteriores.

Fase finalizada. No se inició ninguna fase posterior.

# Habilitación futura de la transición real — no ejecutada

Esta fase solo desarrolla y prueba. No genera claves, permisos o cuarentenas
reales, no activa la barrera, no cambia `.env` ni Wamp y no ejecuta migraciones.
La autoridad y el firmante deben establecerse con autorización posterior.

## Canales separados

El comando sin opciones sigue siendo una simulación de solo lectura. No crea
directorios y no consume permisos. `--ejecutar` sin `--real` conserva el cierre
SQLite :memory: + almacenamiento temporal. No existe fallback a MySQL.

El canal real requiere simultáneamente `--ejecutar --real`, confirmación
`INCORPORAR_DOCUMENTOS_REALES`, todos los parámetros anteriores de inventario,
lote y cuarentena y estas opciones nuevas:

- `--autorizacion`: archivo externo firmado.
- `--autorizacion-sha256`: huella completa aprobada de ese archivo.
- `--clave-sha256`: huella completa aprobada del archivo de clave pública.

No existe `--force`, variable de entorno ni opción para omitir los controles.
No ejecutar ese canal en la fase de desarrollo. El canal real rechaza testing
antes de abrir cualquier conexión MySQL; sus políticas se prueban con contextos
ficticios y el motor común se prueba con SQLite y archivos temporales.

## Autoridad externa y uso único

Ubicación fija: `LOCALAPPDATA/ITM-DocumentAuthorization/clave-publica.txt`, con la
clave pública Ed25519 en Base64. El registro está en la subcarpeta `usos`, que
debe existir con ACL privadas. No hay generación automática ni confianza en una
clave indicada por otra opción CLI. La huella de la clave debe aprobarse por un
canal independiente antes de ejecutar. El repositorio no contiene claves reales.

La clave privada de firma nunca debe estar en el repositorio, en el servidor de
ejecución ni en argumentos de comandos. Un operador autorizado mantiene el
firmante separado y firma solo después de revisar los artefactos y el contexto.
No se implementa un comando de autofirma que pudiera autorizarse a sí mismo.

El sobre es JSON con `payload` y `firma` Base64. La firma Ed25519 cubre los bytes
JSON exactos decodificados de payload; no se vuelve a serializar para verificar.
Se revisan la firma y las huellas del sobre y de la clave leída. El payload v1
contiene:

- `accion`: `transicion-documental-inicial`, `id`: 32 caracteres hex únicos.
- `entorno`: el resultado íntegro de `EntornoTransicionReal::preparar()`.
- `solicitud`: inventario, sha256, cuarentena y lote, exactamente como se invocarán.
- `inventario`, `respaldo`, `auditoria`: cada uno con ruta absoluta y SHA-256.
- `respaldo_verificado`: true, tras la revisión independiente del respaldo.
- `ventana`: timestamps enteros `desde` y `hasta`, duración máxima 30 minutos,
  `confirmada: true` y `escritores_externos_revisados: true`.

La validación solo lee. Después de repetir las comprobaciones bajo flock
exclusivo, se crea `<id>.consumida` con apertura exclusiva `xb` y sincronización.
Un proceso competidor no puede consumirlo de nuevo. El archivo NO se elimina
si falla la copia, la transacción o el proceso; una reanudación requiere un
permiso nuevo, con ID distinto y nuevo respaldo/auditoría revisados. Se conserva
el mismo lote e inventario cuando sus datos siguen siendo idénticos, de forma
que el motor reconoce las copias y versiones ya completadas.

Ni borrar registros de consumo ni restaurar una copia antigua del registro es
una recuperación válida: permitiría replay. Proteger y respaldar ese registro
como evidencia de autorización. Un administrador local capaz de reemplazar la
autoridad y los archivos de consumo queda fuera del aislamiento que ofrece la
aplicación; las ACL y la aprobación independiente de huellas son indispensables.

## Contexto real y auditoría

Solo consola, entorno local, Windows, MySQL 8.4.7 en 127.0.0.1:3306/itm. Se valida
la configuración efectiva, incluidas URL/caché, se rechazan read/write hosts y
sockets y se revisan driver PDO, DATABASE(), puerto, versión y UUID reales.
El contexto liga proyecto, rutas, barrera y huellas de las 28 tablas originales.
La tabla documental debe existir con las restricciones que comprueba el motor.
Las tres opciones documentales deben estar false y la barrera debe estar true.
No se cambia ninguna de estas opciones automáticamente.

La auditoría externa JSON v1 requiere: `revisada_en`, procesos/tareas revisados,
escritores externos descartados, restauración verificada, `respaldo_sha256`,
`datos_originales_sha256`, lista completa `publicaciones_http` y lista
`configuracion_http` de rutas + SHA-256 de todos los archivos de configuración
efectiva revisados, incluidos Include y Alias. Debe tener menos de cinco minutos
al validar/consumir y pertenecer a la ventana confirmada. Se comparan de nuevo
sus huellas; no se ejecuta el SQL ni la configuración revisada.

La firma atestigua el trabajo del operador y la completitud de su inventario de
publicación/escritores; el programa no puede demostrar que el operador omitió
un Alias o un proceso externo. La comprobación automática revisa las huellas,
las raíces declaradas, el respaldo no vacío con terminación de mysqldump y
USE itm, y las conexiones/transacciones/colas/eventos visibles en MySQL. No
equivale a restaurar el SQL: la restauración debe verificarse por separado y
atestiguarse sobre la huella exacta, antes de emitir el permiso.

## Rutas y ACL: impedimentos actuales

El canal real exige destinos y barrera fuera del repositorio y del www de Wamp,
además de fuera de todas las publicaciones declaradas en la auditoría. Es una
restricción conservadora, no se aceptan excepciones implícitas de `.htaccess`.
Se rechazan raíces privadas superpuestas y rutas redirigidas por enlaces.
La cuarentena real queda fijada a LOCALAPPDATA/ITM-PrivateQuarantine.

`transition_control_root` es null por defecto; permite preparar una futura raíz
de barrera externa sin cambiar la ubicación predeterminada ni activarla ahora.
En pruebas cualquier raíz personalizada también debe ser temporal. No debe
compartirse un flock entre dos ubicaciones distintas: todos los procesos deben
recibir la misma configuración, desplegarse y drenarse antes de la ventana.

La inspección actual encontró DocumentRoot www y ACL amplias en el directorio
privado. La configuración actual NO satisface el canal real. Resolver mediante
autorización separada la ubicación privada y la barrera, sus ACL y el despliegue
coherente antes de crear una autoridad o permiso real. No se cambió nada aquí.
La revisión DACL es solo lectura y rechaza accesos de principals distintos del
usuario actual, SYSTEM y administradores. No garantiza acceso efectivo de otro
usuario de servicio ni sustituye una prueba de escritura/enlace duro autorizada.

## Escritores y concurrencia

Las tres rutas POST y la generación GET de PDF mantienen el flock compartido
durante toda la petición: generación, guardado, cambios de referencia y borrado
del documento anterior. Save/delete de Inscripcion y servicios de guardado,
historial y descarte compensatorio de un archivo nuevo comparten la barrera.
La aprobación/rechazo administrativos no sustituyen PDFs; sus escrituras de
Inscripcion participan mediante el modelo. No se modifica su lógica académica.

Query builder, SQL directo, scripts externos y otros hosts NO quedan bloqueados
por este mecanismo. Una tarea CLI debe envolver su operación documental completa
en `ControlTransicionDocumental::operacion()`. No basta con bloquear el save al
final de una generación. La revisión de procesos/tareas debe identificar o detener
esos escritores; la ausencia instantánea de conexiones SQL no prueba su ausencia.
La barrera exclusiva drena únicamente los escritores que ya usan la barrera.

## Procedimiento propuesto 2G-2B-2 — requiere autorización posterior

1. Resolver los impedimentos de publicación/ACL/configuración mediante aprobación
   específica. Preparar raíces privadas, autoridad, usos y barrera externa; no
   poner claves privadas en el servidor. Autorizar aparte las pruebas de acceso
   efectivo y de publicación con archivos ficticios, nunca documentos reales.
2. Desplegar la misma barrera en HTTP y workers, revisar cachés, inventariar
   servicios, tareas programadas, schedulers, colas, scripts manuales y conexiones
   externas. Detener o adaptar los no cooperativos con autorización. Drenar las
   operaciones anteriores; confirmar que no hay solicitudes web en curso.
3. Reservar la ventana y obtener inventario actualizado completo en privado.
   Respaldar estructura y datos de las 29 tablas; verificar huellas, conteos y
   restauración aislada del respaldo exacto. Conservar todos los anteriores.
4. Revisar las seis asociaciones, los 103 sin asociación, los 109 hashes y las
   referencias. Obtener el contexto con el servicio de lectura, revisar toda
   publicación HTTP y cerrar la auditoría fresca. No inferir propietarios.
5. Fuera del ejecutor, firmar un permiso de ID nuevo vinculado a los artefactos,
   rutas, contexto y ventana. Aprobar independientemente las huellas del sobre y
   de la clave pública y obtener autorización expresa para la ejecución real.
6. Invocar el canal real con todas las opciones descritas. Verificar de nuevo
   precondiciones, adquirir exclusión, consumir el permiso y conservar el bloqueo.
   Copiar primero; después insertar las seis versiones en una transacción.
7. Comprobar 109 copias/huellas y originales intactos, seis versiones iniciales
   vigentes, documento_path y datos originales intactos. No borrar originales.
8. Si falla, no limpiar globalmente ni reutilizar la autorización. Inspeccionar
   copias, `.part`, manifiestos y SQL; obtener permiso nuevo para el mismo lote
   solo si inventario y referencias siguen siendo idénticos.
9. No retirar el bloqueo para volver al flujo heredado. Activación de opciones,
   comprobación de descargas, retirada de bloqueo real y eliminación de copias
   públicas requieren alcance/autorización separados. El comando de retirada
   conserva su limitación a pruebas en esta entrega.

Plantilla de invocación futura (reemplazar todos los marcadores; no ejecutar en
esta fase ni usar valores de pruebas):

```powershell
php artisan inscripciones:transicionar-documentos --ejecutar --real --confirmar=INCORPORAR_DOCUMENTOS_REALES --inventario="RUTA_INVENTARIO" --sha256=SHA_INVENTARIO --cuarentena="RUTA_CUARENTENA" --lote=LOTE --autorizacion="RUTA_PERMISO" --autorizacion-sha256=SHA_PERMISO --clave-sha256=SHA_CLAVE_PUBLICA
```

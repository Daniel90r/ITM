# Transición documental: herramienta preparada, ejecución real bloqueada

Actualización posterior: ver `documentos-inscripcion-fase-2g-2b-1.md` para el
canal real con permiso firmado. El canal SQLite y sus protecciones se conservan.

Esta entrega no autoriza ni permite ejecutar la transición en MySQL. No cambia
`.env`, no habilita almacenamiento privado ni historial y no elimina originales.

## Comandos

`php artisan inscripciones:transicionar-documentos` simula por defecto. Consulta
referencias y metadatos, verifica originales y destinos existentes y devuelve
solo cantidades, bytes y contenidos repetidos. No crea discos, directorios,
manifiestos, bloqueos ni registros. Puede consultar MySQL en este modo de lectura.

La ejecución requiere todas estas opciones:

- `--ejecutar`
- `--confirmar=INCORPORAR_DOCUMENTOS`
- `--inventario=<JSON privado revisado>`
- `--sha256=<SHA-256 completo autorizado del JSON>`
- `--cuarentena=<directorio privado externo>`
- `--lote=<identificador estable>`

**El cierre de seguridad solo admite APP_ENV=testing, SQLite :memory: efectivo y
raíces temporales externas al proyecto. No existe una opción CLI para omitirlo.**
Además exige las tres opciones documentales desactivadas, la barrera preparada
y la estructura con sus restricciones. Cualquier ejecución real necesita nueva
autorización y una revisión del cierre; esta versión no está habilitada para 2G-2B.

## Inventario autorizado

El servicio `TransicionDocumental::planificar()` devuelve un array determinista:

```json
{
  "formato": 1,
  "referencias": [{"id": 1, "ruta": "solicitudes/ficticio.pdf"}],
  "archivos": [{
    "id": "SHA256 de public + byte NUL + ruta relativa",
    "ruta": "solicitudes/ficticio.pdf",
    "inscripcion_id": 1,
    "bytes": 123,
    "sha256": "SHA256 completo de los bytes"
  }]
}
```

Es solo un ejemplo de esquema, no datos utilizables como autorización. Para un
archivo sin asociación, `inscripcion_id` es null. Todas las referencias, incluso
las vacías, quedan incluidas para detectar cambios. No se infieren propietarios.
En pruebas se serializa ese array a un archivo temporal y se autoriza su huella.
Esta entrega no crea un inventario de ejecución para los documentos reales.
El JSON completo y su SHA deben revisarse independientemente antes de ejecutar.

## Copia y recuperación

Se valida cada ancestro para rechazar symlinks/junctions. Se copia a un `.part`
exclusivo, se sincroniza y se comparan origen, copia, tamaño y SHA-256. La
publicación usa un enlace duro del mismo volumen que falla si el destino existe;
jamás se usa rename con reemplazo. Si el sistema de archivos no soporta esa
operación, se detiene y conserva el temporal. Solo se retira el `.part` propio
tras verificar el archivo final. No se borran originales, respaldos ni copias
verificadas. Los destinos idénticos se reutilizan; los distintos detienen el lote.

La cuarentena utiliza `<raíz>/<lote>/<identificador-estable>.pdf`, con un manifiesto
privado inmutable. Se detectan contenidos repetidos, pero cada ruta conserva su
identificador y no se asocia automáticamente a una inscripción. Los manifiestos
también se publican mediante temporales; una interrupción no publica JSON parcial.

Después de verificar todas las copias se bloquean las referencias en orden de ID
y se registran versiones iniciales en una sola transacción. Se reutiliza el
servicio de historial con una entrada limitada a la barrera exclusiva, sin activar
opciones ni modificar documento_path. Fecha de incorporación real, versión 1,
disco inscripciones, vigente. Un registro inicial idéntico se reutiliza; cualquier
historial distinto requiere revisión. No es un migrador general de historiales
ya existentes en el disco público.

Un fallo SQL revierte las inserciones, no las copias. El manifiesto y las huellas
permiten repetir el mismo lote. `completado.json` solo se publica después del
commit. Si falla ese marcador, se inspecciona la BD y se reintenta de forma
idempotente. Los `.part` abandonados se conservan para revisión individual, nunca
se ejecuta una limpieza global. Un SHA coincidente no certifica la validez
semántica de un PDF que ya estaba corrupto al elaborar el inventario.

## Barrera cooperativa, desactivada por defecto

`documentos_inscripcion.transition_guard_enabled` permanece false. No se activa
en esta entrega. Cuando se habilite de forma autorizada en TODOS los procesos,
las cuatro rutas de envío/generación mantienen un flock compartido durante toda
la petición, incluyendo el borrado del PDF anterior. Los servicios de guardado e
historial y save/delete de Inscripcion también participan para tareas Eloquent.
Otras tareas automatizadas deben envolver la operación completa en
`ControlTransicionDocumental::operacion()`, antes de generar o sustituir archivos.

El transicionista exige flock exclusivo no bloqueante sobre el mismo archivo.
Si un escritor o transicionista ya lo tiene, se detiene. Después crea un marcador
persistente: las nuevas operaciones reciben 503 aun después de finalizar el CLI.
El bloqueo NO se libera automáticamente al terminar o fallar la transición.

`inscripciones:bloqueo-documental` solo consulta el estado, sin crear directorios.
Las opciones `--activar` y `--retirar` requieren
`--confirmar=CAMBIAR_BLOQUEO_DOCUMENTAL`, y también están limitadas al aislamiento
de pruebas. Se prueban activación, estado, retirada y exclusión de escritores.

Antes de una futura activación real: inventariar procesos/colas/tareas, desplegar
la barrera coherentemente, reiniciar workers autorizados, comprobar sus config
caches, drenar operaciones iniciadas antes del despliegue y confirmar todos los
escritores. SQL masivo (query builder/raw), scripts que escriben directamente en
Storage y procesos externos no respetan automáticamente un flock PHP. Si existen,
hay que detenerlos o adaptarlos antes de la ventana; no afirmar aislamiento
universal. El mecanismo es local, no un bloqueo distribuido para varios hosts.

No reabrir el flujo público heredado tras incorporar el historial: sus reintentos
podrían cambiar referencias y eliminar originales. La retirada del bloqueo debe
coordinarse con una activación privada autorizada y las pruebas de descarga.
La herramienta rechaza retirar la barrera si ya existen versiones y el flujo
privado con historial no está habilitado en el proceso que solicita retirarla.
Esto no reemplaza verificar la configuración efectiva de todos los workers.

## Antes de 2G-2B

Nueva autorización, inventario completo y respaldo actualizado; revisión de la
habilitación real (actualmente cerrada), raíz de cuarentena fuera de Apache y
repositorio, ACL privadas Windows, raíces Laravel y DocumentRoot público;
capacidad de disco y soporte de enlaces duros; control de procesos concurrentes.
La ubicación propuesta de cuarentena real es LOCALAPPDATA/ITM-PrivateQuarantine,
pero NO se crea ni se permite ejecutar allí en esta entrega de pruebas.

Autorizar separadamente copias e inserciones, activación y retirada pública. Una
copia privada no revoca el acceso a un original que siga en /storage. No cambiar
las rutas autenticadas existentes ni eliminar copias públicas durante esta fase.

## Pruebas

Usar exclusivamente `tests/documentos-isolated-bootstrap.php`, SQLite :memory:
y almacenamiento temporal; nunca ejecutar scripts de depuración o migraciones
reales. No utilizar los PDF ni respaldos reales como fixtures.

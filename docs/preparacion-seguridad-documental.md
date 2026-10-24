# Preparación técnica de privacidad documental ITM

## Alcance autorizado y resultado

Esta fase prepara pruebas y un procedimiento; **no autoriza aplicar configuración, activar banderas, copiar documentos reales, escribir metadatos reales ni ejecutar la transición**. Solo se añaden pruebas, este documento y una configuración de ejemplo no incluida por el servidor activo.

El inventario de referencia de la investigación es 109 PDF, 5.132.534 bytes, seis referencias actuales y 103 sin asociación verificable. Es una referencia, no una autorización para procesar cualquier inventario futuro: si cambian cantidades, referencias o huellas se detiene la operación y se revisa un nuevo plan.

Componentes reutilizados, sin modificarlos: `DocumentosInscripcion`, `DocumentoInscripcionController`, `HistorialDocumentosInscripcion`, `TransicionDocumental::planificar/resumen`, validaciones de `ControlTransicionDocumental`, discos y rutas existentes. Las pruebas no invocan `ejecutar`, `ejecutarReal` ni el comando de transición.

## Archivos de preparación

- `docs/examples/apache-solicitudes-private.conf.example`: bloqueo por URL y por directorio físico; no aplicado.
- `tests/Feature/DocumentalApachePreparationTest.php`: Apache independiente y descargas Laravel sobre recursos ficticios.
- `tests/Feature/DocumentalInventoryPreparationTest.php`: inventario ficticio 109/6/103 y detección de inconsistencias, sin transición.
- Este procedimiento.

Ningún archivo de aplicación, configuración activa, migración o documento real se modifica en esta fase.

## Restricción HTTP propuesta

El ejemplo requiere definir `DOCUMENTAL_PUBLIC_ROOT` como la raíz efectiva del disco público, con barras `/`, sin barra final. Para este despliegue, el valor a revisar antes de aplicar sería `C:/wamp64/www/ITM/storage/app/public`. **No se añadió ese Define ni ningún Include a Apache activo.**

Se propone incluir el ejemplo en ámbito global para cubrir todos los VirtualHost que puedan publicar ese disco:

1. `<Directory "${DOCUMENTAL_PUBLIC_ROOT}/solicitudes"> Require all denied`: protege el directorio físico y accesos mediante Alias.
2. `<LocationMatch "(?i)^/storage/solicitudes(?:/|$)"> Require all denied`: protege la URL y su acceso mediante la junction de Windows.

No se bloquea `/storage` completo ni el prefijo diferente `/storage/solicitudes-publicas`. No hay excepciones por método, IP local o cookie. No se desactiva el acceso HTTP para el directorio privado: ese directorio no debe publicarse en absoluto. No se deshabilita la lectura local necesaria para que PHP entregue un documento después de autorizarlo.

La preparación reproduce Apache 2.4 de Wamp, `Require local`, `FollowSymLinks`, un `DocumentRoot` temporal, una junction `web/storage` al disco público ficticio y un Alias alternativo. Usa configuración mínima independiente, `httpd -X -f <archivo temporal>`, puerto libre en **127.0.0.1**, PID y logs temporales; no carga `httpd.conf` ni VirtualHost activos, no usa el puerto 80 y no administra servicios Windows. La prueba detiene únicamente su proceso y retira únicamente su junction. El esquema se debe volver a validar sobre una réplica completa de los Alias, HTTPS y proxies del despliegue definitivo.

Casos HTTP comprobados con GET y HEAD, sin cookies ni Authorization:

- Documento ficticio bajo `/storage/solicitudes/`: 403.
- Ruta descendiente inexistente: 403, sin revelar existencia.
- Segmento codificado `%73olicitudes`: 403.
- Mayúsculas en `SOLICITUDES`: 403, también en el sistema de archivos Windows.
- Alias alternativo al mismo directorio: 403.
- Texto público, recurso público bajo `/storage/imagenes/` y PDF ficticio bajo `/storage/solicitudes-publicas/`: 200.
- Las respuestas denegadas no contienen el marcador del PDF; HEAD no devuelve cuerpo.
- El mismo archivo estático denegado puede entregarse mediante autorización Laravel durante una compatibilidad controlada, solo en pruebas; después se verifica la descarga privada con lectura heredada desactivada.

No se hicieron solicitudes HTTP a documentos reales ni al servidor activo para esta preparación.

## Cómo ejecutar las pruebas aisladas

Desde la raíz del proyecto, en PowerShell:

```powershell
$env:ITM_APACHE_TEST_BIN = 'C:/wamp64/bin/apache/apache2.4.65/bin/httpd.exe'
$env:ITM_REQUIRE_APACHE_TESTS = '1'
php -d xdebug.mode=off -d memory_limit=768M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php --filter 'DocumentalApachePreparationTest|DocumentalInventoryPreparationTest|DocumentosInscripcionTest|HistorialDocumentosInscripcionTest|AlmacenamientoDocumentalExternoTest' --do-not-cache-result
```

Estas variables son solo del proceso de pruebas; no son banderas de la aplicación. El bootstrap impone SQLite `:memory:`, discos falsos y almacenamiento temporal, y las nuevas pruebas verifican el controlador PDO y las raíces antes de preparar datos. `ITM_REQUIRE_APACHE_TESTS=1` convierte la falta del binario en fallo, para evitar aprobar esta fase con la prueba HTTP omitida.

Resultado final: **51 pruebas aprobadas, 681 aserciones, cero fallos y cero errores**, 23,971 segundos; ninguna prueba omitida. No se suman ejecuciones anteriores. Se corrigieron en el arnés inicial la normalización de rutas de la junction y el cierre del proceso aislado en Windows; la ejecución final termina automáticamente su propio Apache, sin administrar servicios ni detener el servidor activo.

La batería existente añade comprobaciones de dueño/administrador, invitado, otros alumnos y docentes, versiones, rutas manipuladas, enlaces/junctions, integridad SHA-256, generación de PDF de adulto y menor, subidas y reintentos privados. También comprueba recuperación ante fallo de escritura o metadatos y conservación de documentos previos, siempre ficticios. No se ejecuta `TransicionDocumentalTest`, porque contiene llamadas al motor de transición; su ejecución aislada requiere una autorización posterior que la permita expresamente.

## Inventario y respaldo: procedimiento preparado, no ejecutado

1. **Preflight de lectura:** comprobar raíz pública efectiva, raíz privada externa, cuarentena, ACL, enlaces/Alias/HTTPS, esquema existente de `documentos_inscripcion`, espacio disponible y todas las referencias `documento_path`. No imprimir datos de alumnos ni nombres de PDF en logs públicos.
2. **Inventario en memoria:** usar `TransicionDocumental::planificar()` y `resumen()` dentro de una transacción MySQL `READ ONLY`, finalizada con rollback. El plan contiene por archivo ruta relativa, bytes, SHA-256 e inscripción asociada o null; también conserva las referencias originales. `planificar` no crea copias, versiones, barreras ni directorios. Alternativa de resumen: `php artisan inscripciones:planificar-documentos`, **sin `--manifest`**. No ejecutar ninguno como paso automático de esta preparación.
3. **Manifiesto futuro autorizado:** después de aprobar su destino, serializar el plan exacto a una ruta privada externa, con creación exclusiva, fecha UTC y huella SHA-256 del JSON completo. Ese documento contiene rutas y referencias sensibles: no guardarlo en el repositorio, `public`, capturas ni chats. Conservar sus bytes exactos para la validación firmada. El manifiesto del planificador y el plan de `TransicionDocumental` no son formatos intercambiables; usar el exigido por el motor.
4. **Respaldo futuro autorizado:** copiar los 109 originales, sin modificar su contenido, a un destino de respaldo privado separado del destino operativo y de la cuarentena. Crear un manifiesto de respaldo con ruta, tamaño y SHA-256 por archivo. Verificar todas las copias contra el inventario y calcular la huella del manifiesto. Conservar ACL restrictivas y comprobar restauración con datos ficticios antes de actuar sobre los reales.
5. **Respaldo lógico futuro autorizado:** incluir las referencias de inscripción y metadatos documentales en el respaldo MySQL correspondiente al corte. La restauración debe evitar sobrescribir otras operaciones académicas o financieras posteriores. No se generó un dump real en esta fase.

No se crea aquí un manifiesto persistente de archivos reales ni se copia ningún PDF real. Las huellas reales de control de esta fase se calculan solo para verificar que los originales no cambian; no sustituyen el inventario aprobado de ejecución.

## Orden exacto propuesto para una implementación posterior

Cada punto que implique modificación requiere autorización posterior; lo siguiente es un procedimiento, no instrucciones ejecutadas.

1. **Aprobar alcance y responsables:** destinos privados, política de acceso, cuarentena, respaldo, conservación, ventana de mantenimiento y tratamiento de marcadores antiguos. Revisar todos los VirtualHost y Alias; no asumir que una excepción local protege otros nombres del servidor.
2. **Preparar release y ensayarlo:** modificar los enlaces heredados de Solicitudes para usar rutas protegidas; revisar la activación coordinada de generación, subida, reintentos e historial. Ensayar la configuración HTTP y restauración sobre una réplica ficticia. No activar `enabled` por separado: puede causar 404 o bloquear reemplazos si las copias/historial no están disponibles.
3. **Parar escritores y establecer mantenimiento documental:** inventariar tráfico HTTP, procesos CLI, colas, tareas programadas, importaciones y acceso SQL directo. Revisar que todos los escritores pasen por la barrera antes de habilitarla. La barrera es cooperativa y no detiene por sí sola Query Builder, SQL directo ni procesos externos.
4. **Aplicar el bloqueo HTTP aprobado:** validación sintáctica de la configuración candidata y recarga controlada de la instancia correcta. Comprobar GET y HEAD con un recurso ficticio seguro en la réplica y probar otros recursos públicos. Nunca usar `httpd -k shutdown` o una orden sin configuración/instancia explícita durante las pruebas.
5. **Tomar corte, respaldo y plan aprobado:** con escritores congelados, generar los manifiestos y respaldo de los pasos anteriores. Si el plan no coincide exactamente con lo aprobado, detenerse. No asumir que los números 109/6/103 siguen vigentes.
6. **Verificar el canal real existente:** el motor exige entorno/conexión exactos, ACL y raíces fuera de web, inventario de publicaciones HTTP y marcadores, barrera habilitada y autorización externa firmada de uso único con su canal de validación. No fabricar permisos ni puentear comprobaciones. `enabled`, `history_enabled` y `legacy_read_enabled` deben permanecer desactivadas durante la incorporación. Esta fase no provisiona esos requisitos ni ejecuta el motor.
7. **Migración autorizada:** copiar los seis archivos asociados al disco privado conservando ruta relativa y `documento_path`; incorporar versión inicial con bytes y SHA-256. Copiar los otros 103 a cuarentena privada según los identificadores del manifiesto, sin crear inscripciones ni inferir propietarios por nombre o hash. El motor existente comprueba plan antes/después, conserva originales y permite reanudar copias idénticas.
8. **Validar antes del corte funcional:** comprobar integridad individual, seis asociaciones/versiones esperadas, 103 entradas de cuarentena, ausencia de colisiones y que estados académicos/pagos/notas no cambien. La ejecución real escribiría metadatos en MySQL; requiere autorización expresa aunque no cambie el esquema.
9. **Activar release privado coordinado:** activar almacenamiento privado e historial, mantener lectura heredada desactivada; comprobar descargas de administrador y alumno propietario, rechazo de terceros, generación, subida y reintentos con conservación de versiones. Retirar la barrera únicamente bajo el mecanismo exclusivo cuando el flujo privado esté preparado.
10. **Retirada de originales públicos:** operación separada, expresamente autorizada, después de verificar respaldo, destino privado y cuarentena. El motor existente **no los retira**. El bloqueo HTTP debe mantenerse incluso mientras aún existen los originales.
11. **Cierre:** repetir inventario e integridad, revisar logs sin contenido personal, confirmar que no reaparecen archivos públicos, probar los cinco informes y establecer política de conservación. Registrar el lote, responsables y resultados en ubicación privada.

No se fija aquí un comando listo para ejecutar la transición real: depende de rutas aprobadas, manifiesto exacto y permiso firmado externos que esta fase no crea ni autoriza.

## Compatibilidad y escritores que deben verificarse

- `AlumnoDashboardController`: generación PDF, subida, núcleo de creación/reutilización de solicitud pendiente, compensación de archivo nuevo y tratamiento del documento anterior en reintentos.
- `DocumentosInscripcion`: almacenamiento privado, validación de ruta, rechazo de enlaces y descarte exclusivo de archivos nuevos no referenciados.
- `HistorialDocumentosInscripcion` y modelo documental: una vigente, versiones únicas e integridad, sin atribuir versiones antiguas a una inscripción inventada.
- `DocumentoInscripcionController`, rutas administrativas y de alumno: autorización antes de leer; alumno solo propietario; versiones ligadas a esa inscripción; cabeceras privadas.
- Vista administrativa `solicitudes.blade.php`: retirar dependencia de `asset('storage/...')` en el release futuro. Vista del alumno: conservar descarga del documento presentado cuando el flujo está activo.
- `Inscripcion::save/delete` y control de transición: no desactivar su barrera durante el corte.
- Procesos ajenos al código HTTP: CLI, cron, workers, scripts de importación, herramientas SQL y sincronización. Requieren inventario operativo; no puede certificarse su ausencia únicamente con una búsqueda del repositorio.
- Los cinco informes nuevos no dependen de los PDF históricos y deben conservar su comportamiento. Boletas y firmas utilizan otros mecanismos; no bloquear todo el disco público indiscriminadamente.

Los marcadores públicos antiguos deben responder 403/404. La compatibilidad se mantiene en la interfaz y las rutas autorizadas. Un alias autenticado para marcadores antiguos sería un alcance adicional: requeriría una solución específica compatible con el bloqueo global, referencia exacta y autorización, sin lectura arbitraria de rutas ni excepciones de acceso estático.

## Reversión segura y recuperación

- **Antes de escribir metadatos:** conservar bloqueo HTTP y mantenimiento; las copias parciales quedan identificadas por lote y huella. Reanudar solo con el mismo plan aprobado. Nunca sobrescribir un destino diferente.
- **Después de incorporar versiones:** no volver a activar el escritor público. Mantener mantenimiento hasta reparar el flujo privado o restaurar un respaldo coherente aprobado. El control existente impide retirar la barrera si hay historial y el flujo privado no está listo.
- **Después de reabrir la aplicación:** no restaurar globalmente una base antigua ni borrar versiones nuevas. Generar un inventario de diferencias y aprobar una recuperación puntual; conservar todos los archivos nuevos y metadatos posteriores al corte.
- **Originales retirados:** restaurar desde el respaldo a una raíz privada, verificando tamaño y SHA-256. No restaurarlos a un directorio servido por HTTP. Una reversión funcional no autoriza recuperar la exposición pública.
- El sistema de archivos y MySQL no forman una transacción única: documentar estado del lote, mantener las copias y comprobar integridad tras cada fallo. No borrar cuarentena, respaldo o manifiestos como limpieza automática.

Pruebas preparadas para una autorización posterior de transición aislada: fallos parciales de copia, caída antes/después del commit, reanudación idempotente, colisión de destinos, alteración del origen, escritores concurrentes, bloqueo conservado, respaldo/restauración y pérdida de permisos. La batería `TransicionDocumentalTest` ya contiene escenarios de esos tipos; no se ejecutó en esta fase.

## Autorizaciones pendientes y límites de aceptación

Requieren aprobación explícita: aplicar/reload de Apache, cambios de código de producción y enlaces, activar banderas/barrera, crear manifiestos o respaldos reales, provisionar ACL/raíces/autoridad externa, ejecutar transición (incluso un ensayo de transición si se mantiene la prohibición actual), escribir historial real, retirar originales públicos y decidir conservación/acceso de la cuarentena.

La aprobación de estas pruebas no certifica todos los proxies/VirtualHost de un despliegue futuro ni autoriza publicar el sistema. No se modificó el servidor activo, la base real ni los 109 originales, y no se hizo commit ni push.

Comprobación de cierre: el inventario agregado de rutas relativas, tamaños y SHA-256 de los 109 originales coincide exactamente antes/después (5.132.534 bytes). También coinciden las huellas de `.env`, `httpd.conf` y `httpd-vhosts.conf` activos. Una consulta MySQL READ ONLY, finalizada con rollback, confirmó seis referencias, cero versiones y las cuatro banderas documentales desactivadas. No se persistió un manifiesto real ni se copiaron documentos.

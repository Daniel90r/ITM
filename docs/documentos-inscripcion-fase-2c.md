# Historial documental preparado, NO activado

Migración pendiente (solo se ejecuta en SQLite durante las pruebas):
`database/migrations/2026_10_19_040000_create_documentos_inscripcion_table.php`.

## Estructura e invariantes

`documentos_inscripcion`: ID INT UNSIGNED, inscripción INT UNSIGNED con FK
RESTRICT, versión, ruta relativa, disco controlado (`inscripciones` o `public`),
SHA-256, bytes y fecha de registro. `vigente` solo admite `1` o NULL: UNIQUE
(inscripción, vigente) impide dos vigentes. UNIQUE (inscripción, versión)
impide números repetidos; UNIQUE (disco, ruta) impide reasignar un archivo.
Las versiones históricas llevan NULL. La transacción deja exactamente una
vigente para cada historial registrado; las inscripciones sin documento no
requieren historial. No se agregó ni modificó ninguna columna académica.

## Activación y compatibilidad

Siguen en false por defecto:

- INSCRIPCION_DOCUMENTOS_PRIVADOS
- INSCRIPCION_DOCUMENTOS_LEGACY_READ
- INSCRIPCION_DOCUMENTOS_HISTORIAL

No modificar `.env` en esta fase. Con todo desactivado no se consulta la tabla
nueva y se mantienen los reintentos y eliminación heredados. El modo 2B sin
historial sigue bloqueando reemplazos de documentos. Para registrar versiones
se requieren privado + historial y la migración aplicada; si falta la tabla
se devuelve un error de validación antes de crear archivos. Las rutas del
historial devuelven 404 mientras no se habiliten sus requisitos.

El orden futuro obligatorio es: autorización y respaldo -> migración limitada
a su archivo -> verificación -> incorporación/traslado autorizado y comprobado
-> activación coordinada. Nunca activar opciones antes de preparar las referencias
y discos; una fila histórica en disco público necesita lectura legacy hasta que
una operación autorizada migre también sus metadatos. No basta con copiar bytes.

## Transacciones y reintentos

Se mantienen reglas de curso, cupos, encargado y estados. El núcleo de envío
bloquea alumno -> curso -> inscripción. Dentro de esa misma transacción, el
servicio vuelve a bloquear la inscripción y registra historial, versión vigente
y `documento_path`; los índices refuerzan la exclusión bajo concurrencia.

Un reintento con documento distinto conserva el anterior y agrega MAX(version)+1.
Una presentación idéntica por SHA-256 reutiliza su versión y archivo existentes,
tras verificar integridad: no crea otra copia persistente ni otro número.
Puede volver a estar vigente una versión histórica idéntica; esto documenta
versiones de contenido, no un registro separado de cada intento de envío.
Registrar cada intento idéntico como evento requeriría una ampliación explícita.

Si fallan los metadatos o el guardado de la inscripción, SQL revierte todo. La
compensación solo acepta la ruta UUID recién creada y no borra archivos con
metadatos confirmados. No elimina históricos. Si falla la compensación por un
problema físico, el archivo nuevo queda para inventario; no borrar automáticamente
supuestos huérfanos. Un cierre abrupto del proceso puede dejar un archivo nuevo
sin referencia, porque archivos y SQL no forman una transacción distribuida.

## Incorporación futura de existentes

`HistorialDocumentosInscripcion::incorporarReferenciaVerificada` prepara la
incorporación idempotente de UNA inscripción con referencia actual comprobable.
No busca coincidencias de nombres y no asocia archivos sueltos. Verifica ruta,
disco, tamaño y SHA-256; rechaza colisiones entre copias y asociaciones previas
incompatibles. No copia ni elimina bytes. Un reintento privado puede incorporar
esa referencia comprobada dentro de la misma transacción antes de crear v2.
Nada de esto se ejecutó sobre documentos reales.

El planificador también reconoce asociaciones de versiones históricas, verifica
sus hashes/tamaños y distingue versiones no localizadas. Un PDF anterior con
historial no se clasifica como archivo sin asociación por haber cambiado
`documento_path` a una versión más reciente.

Antes de operar: repetir inventario/manifiesto privado, respaldar referencias y
archivos, verificar hashes, detener escrituras concurrentes y resolver conflictos.
Los 103 sin referencia comprobada deben seguir sin asociación, en reserva según
el futuro traslado autorizado. No sobrescribir destinos ni eliminar copias públicas.
Conservar manifiesto antes/después y aplicar cada referencia solo tras comprobar
sus bytes. Repetir la incorporación no crea otra versión. Cualquier actualización
de disco/ruta tras la copia requiere autorización y verificación adicional.

## Descarga y consulta

Rutas GET/HEAD existentes para vigente, más:

- `/admin/solicitudes/{inscripcion}/documentos`
- `/admin/solicitudes/{inscripcion}/documentos/{documento}`
- `/alumno/inscripciones/{inscripcion}/documentos`
- `/alumno/inscripciones/{inscripcion}/documentos/{documento}`

El listado JSON paginado (20) devuelve metadatos mínimos y enlaces autenticados,
nunca rutas físicas, rutas de disco ni URL pública. Toda consulta autoriza primero
la inscripción y luego busca la versión DENTRO de ella. Alumno ajeno: 404;
docente: sin acceso; visitante: login. Las descargas comprueban tamaño y SHA-256
y mantienen cabeceras privadas/no-store. No regeneran PDF. Documento ausente,
alterado o con ruta inválida: 404. No se agregaron enlaces visibles a producción.

## Pruebas aisladas

Usar `--bootstrap tests/documentos-isolated-bootstrap.php`: fuerza SQLite
`:memory:` mediante el bootstrap existente y además cambia todo `storage_path`
a un directorio temporal nuevo. Esto protege documentos reales incluso frente
a pruebas heredadas que no llaman a Storage::fake(). No ejecuta seeders reales.

Las pruebas cubren transacciones solapadas de dos conexiones a una base SQLite
compartida exclusivamente en RAM, duplicados y restricciones del DDL efectivo.
SQLite no reproduce los bloqueos por fila de InnoDB: no afirmar que se probó
concurrencia real en MySQL. Cualquier validación adicional de ese motor requiere
un entorno descartable autorizado, nunca la base local con alumnos reales.

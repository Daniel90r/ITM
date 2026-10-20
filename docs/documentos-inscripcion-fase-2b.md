# Documentos de inscripción: preparación, sin activación

La fase 2C añade versionado optativo; consultar `documentos-inscripcion-fase-2c.md`.
El bloqueo de reemplazos descrito aquí continúa aplicando al modo 2B sin historial.

Ambas opciones de `config/documentos_inscripcion.php` están desactivadas por
defecto. No se ha modificado `.env`, ni aplicado migraciones, ni trasladado PDF.
Los enlaces y el almacenamiento existentes continúan funcionando; los archivos
públicos siguen expuestos hasta completar el traslado autorizado.

## Componentes preparados

- Disco `inscripciones`: `storage/app/private/inscripciones`, sin URL ni `serve`.
- Nuevos documentos: `solicitudes/{UUID}.pdf`, sin código del alumno.
- GET/HEAD `/admin/solicitudes/{inscripcion}/documento`: auth + administrador.
- GET/HEAD `/alumno/inscripciones/{inscripcion}/documento`: auth + expediente
  y rol alumno; solo propietario, 404 para otra inscripción.
- Control de rutas relativas PDF, prefijo permitido y rechazo de symlinks,
  junctions o ancestros que resuelvan a otra ubicación.
- Descarga con nombre genérico, sin caché pública y sin rutas físicas en errores.

`INSCRIPCION_DOCUMENTOS_PRIVADOS=true` habilitaría descargas y escritura privada
de nuevos documentos, así como los enlaces autenticados. NO habilitar ahora.
`INSCRIPCION_DOCUMENTOS_LEGACY_READ=true` permitiría, temporalmente, lectura de
documentos públicos por el controlador DESPUÉS de autorizar al usuario. No
copia archivos ni elimina el acceso estático antiguo. Una copia privada tiene
prioridad; cualquier colisión se debe resolver previamente en el inventario.

## Reintentos: bloqueo deliberado en el modo aún no activado

Con la opción principal desactivada se conserva la eliminación anterior, sin
cambios en el comportamiento de producción. Con la opción activada, se rechaza
la sustitución de una inscripción que ya tenga documento, antes de generar o
guardar otro. Esta limitación evita perder la asociación histórica mientras
no exista versionado autorizado. No activar este modo en producción hasta
resolverla. Las reglas de estados, aprobación y rechazo no se han cambiado.

La futura propuesta requiere registrar cada versión antes de actualizar la
referencia vigente; conservar bytes, SHA-256, disco y asociación verificable.
Debe preservar las versiones confirmadas y compensar únicamente un archivo
nuevo si su transacción falla. No se creó ninguna migración de versionado.

## Planificador

Comando preparado (no ejecutado sobre documentos reales en esta fase):

    php artisan inscripciones:planificar-documentos
    php artisan inscripciones:planificar-documentos --manifest

El primero solo consulta referencias e inventaría los discos configurados,
incluyendo subcarpetas; imprime cantidades, no nombres de alumnos ni rutas.
No sigue enlaces. El segundo además crea un JSON nuevo mediante apertura
exclusiva en el subdirectorio privado `manifiestos` del disco `inscripciones`.
Contiene rutas relativas, IDs de inscripción, tamaños, SHA-256, referencias
inválidas/no localizadas, archivos sin asociación y colisiones. Verifica el
archivo escrito. Nunca sobrescribe un manifiesto. El JSON contiene información
sensible y no debe publicarse ni incorporarse a Git.
No implementa operaciones de copia, traslado, borrado ni UPDATE de referencias.

## Pendiente de autorización y despliegue

1. Resolver versionado/reintentos y aprobar el plan concreto de activación.
2. Repetir inventario al momento de operar; no asumir que siguen siendo 109.
3. Respaldar documentos y referencias fuera del repositorio y del área web;
   verificar cantidades, lectura y SHA-256. Conservar respaldos anteriores.
4. Coordinar una ventana que impida envíos/reintentos concurrentes. Revisar
   todas las raíces reales y resolver rutas inseguras, faltantes o colisiones.
5. Copiar antes de eliminar, manteniendo bytes y asociación; comparar origen,
   copia y respaldo por SHA-256. No asignar los archivos sin referencia a un
   alumno por aproximación del nombre: conservarlos en reserva privada.
6. Mantener rutas relativas cuando sea posible. Cualquier UPDATE de referencias
   requiere autorización explícita, registro reversible y verificación individual.
7. Verificar descargas autenticadas, contenido idéntico, permisos y cabeceras.
8. Solo con autorización, retirar originales públicos ya verificados y comprobar
   que las URLs estáticas dejan de entregar archivos. No eliminar globalmente
   `public/storage`, pues puede servir otros recursos.
9. Habilitar opciones y regenerar configuración cacheada según el despliegue;
   deshabilitar lectura legacy al completar el traslado. No usar `optimize:clear`
   o `cache:clear` como parte de este procedimiento.

La copia/BD no constituye una transacción única: mantener manifiesto de progreso
y un procedimiento de recuperación individual. Si falla la activación después
de escribir nuevos archivos privados, desactivar el código no basta: hay que
preservar su acceso privado y sus asociaciones, sin republicarlos. No ejecutar
rollback global, regenerar PDF ni restaurar estados académicos para recuperar
documentos. Las cachés o copias descargadas previamente no se pueden revocar.

Las raíces deben permanecer bajo control del servidor: las verificaciones no
sustituyen permisos adecuados frente a un proceso local que cambie enlaces
concurrentemente. No se modificaron permisos reales en esta fase.

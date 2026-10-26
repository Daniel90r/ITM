# Boleta: conducta consolidada y conservación de emisiones

## Alcance de esta fase

El PDF nuevo mantiene cinco columnas: Materia, Promedio de calificación, Conceptos de calificación, Aspectos de conducta y Conceptos de conducta. Agrupa por las correspondencias explícitas de referencia, incluso con un subconjunto real: Windows y Word comparten Responsabilidad con rowspan=2 en ambas columnas. No se acredita por ello el programa completo.

La mediana conservadora está autorizada para documentos nuevos: se ordenan B < MB < E y se selecciona el índice entero `floor((N-1)/2)`. No hay promedio numérico ni redondeo. MB/E da Muy Bueno; B/MB da Bueno; B/MB/E/E da Muy Bueno. Si alguna conducta del grupo está vacía o es inválida (P incluida), el aspecto queda «Pendiente de evaluación». Un aspecto sin materias no se presenta. Inglés y otras materias sin correspondencia explícita quedan fuera del cálculo, con «Sin agrupación definida».

Escala institucional confirmada: B = Bueno, MB = Muy Bueno, E = Excelente, en ese orden. El formulario muestra nombres completos y guarda exclusivamente esos códigos o una evaluación pendiente. El servidor valida la lista cerrada. Un valor histórico inválido no se borra al abrir el formulario: exige una elección explícita antes de guardar. Los datos antiguos no se corrigen automáticamente.

El PDF representa los códigos válidos con sus nombres completos. P y cualquier otro código ajeno se presentan como «Sin evaluación válida», sin conversión ni utilización en una consolidación. Los valores vacíos se presentan como «Pendiente de evaluación». La representación es de solo lectura y no cambia el dato original. Los conceptos académicos E/MB/B/R/D siguen siendo independientes y conservan sus reglas.

Reutiliza `BoletaService::previsualizar()` para obtener las materias que corresponden al alumno: materias activas del curso y opcionales asociadas al alumno en ese curso. Una materia real sin nota figura como «Sin nota», sin inventar una calificación ni influir en el promedio. No se inventan trece materias.

El promedio descargable mantiene la media de los promedios almacenados no nulos, su redondeo visible y el umbral de aprobación existente. El cero es una calificación real y recibe el concepto académico D; la ausencia total de promedios se identifica como «Sin calificaciones». No se guardan modelos ni se cambian fórmulas de registro o emisión.

Se eliminaron el instructor fijo, la duración «dos años» y la afirmación de haber cursado una carrera técnica no confirmada. La tabla presenta los nombres de las materias sin mensajes de asignación docente. No se atribuye un instructor general. La firma histórica del director y los elementos institucionales permanecen.

El estado académico figura fuera de la tabla, separado de los conceptos de conducta. Solo se presenta Aprobado/Reprobado cuando `BoletaService::previsualizar()` confirma que las materias esperadas están completas; en caso contrario se muestra «Pendiente de completar calificaciones» o «Sin calificaciones». No se modifica el cálculo del promedio ni se presupone un programa de trece materias.

Los códigos originales se presentan en una nota discreta «Conducta por materia», sin recuadro de diagnóstico. Puede generarse únicamente la muestra Windows/Word mediante `--caso=produccion-dos-materias`.

La vista `admin.pdf.boleta-prueba`, sin ruta pública nueva, utiliza la misma tabla de cinco columnas que producción, con aviso de muestra. La referencia completa mantiene celdas combinadas 3/3/3/4. No se modifican promedios, conceptos académicos, resultados ni firmas.

## Correspondencia de referencia

Se comprueban nombres completos normalizados (mayúsculas y espacios), no posiciones, códigos adivinados ni coincidencias parciales:

- Responsabilidad: Microsoft Windows, Microsoft Word, Microsoft Excel.
- Relaciones personales: Internet, Microsoft PowerPoint, Microsoft Publisher.
- Iniciativa y confianza en sí mismo: Microsoft Access, CorelDraw, Photoshop.
- Hábitos de estudio y trabajo: HTML, Macromedia Dreamweaver, Mantenimiento de PC, Redes.

Las filas se ordenan explícitamente según esta referencia, sin modificar el orden almacenado. Solo se combinan las materias presentes del mismo curso: no se exige completar las trece ni se crean las faltantes. Materias adicionales, incluido Inglés, quedan separadas con «Sin agrupación definida». Si hay nombres de referencia repetidos o se mezclan cursos, se usa la presentación neutral y se conservan todas las filas.

La consulta de solo lectura del 25/10/2026 encontró únicamente Curso A y Curso B con Windows, Word e Inglés. No permite identificar el programa oficial de trece materias. Falta confirmar su identidad y recibir la fotografía original; la muestra sigue la descripción textual disponible.

## Pendiente de autorización

- Programa al que corresponde el perfil y sus identificadores reales.
- Agrupación oficial de Inglés y otros programas, si corresponde.
- Corrección individual autorizada de los registros históricos inválidos, tras comprobar evidencia docente. P está confirmado como inválido; no debe asignársele equivalencia.
- Cualquier cambio futuro de la regla aprobada debe aplicarse únicamente a documentos nuevos.

## Emisión oficial y descarga histórica

- `previsualizar()` consulta datos actuales sin escribir emisiones. La descarga actual sin emisión usa la mediana y no guarda un PDF oficial.
- `emitir()` sigue guardando los snapshots académicos y la firma histórica. Además, genera el PDF definitivo exclusivamente desde los snapshots recién creados y lo conserva antes de confirmar la transacción.
- El disco `boletas_oficiales` es local, privado y no publica URLs. La ruta determinista es `storage/app/private/boletas-oficiales/{boleta_id}/oficial.pdf`; no requiere columnas nuevas. Usa creación exclusiva (`x+b`), comprobación de escritura completa y sincronización al disco. No se usa un archivo temporal como original.
- La ruta administrativa `GET /admin/boletas/emisiones/{boleta}/pdf` entrega exactamente el archivo original si existe. No consulta notas actuales ni vuelve a renderizarlo. La ruta anterior sigue funcionando y deriva a la emisión cuando corresponde.
- Sin original, `DocumentoBoleta` reconstruye desde `boletas` y `detalles_boletas`, sin relaciones actuales de alumno, materia, curso o notas. Conserva nombres, calificaciones, conductas, promedio, fecha, director y firma guardados. No ejecuta mediana: como los aspectos consolidados no estaban persistidos, muestra «No registrado en la emisión». Incluye la advertencia «Reconstrucción basada en los datos registrados al momento de la emisión. Puede diferir del documento originalmente generado.» No guarda la reconstrucción como original.
- Un snapshot académico incompleto produce un error explícito; nunca se completa desde datos actuales. Una firma histórica no disponible queda vacía; no se toma la del director actual. Las rutas de firma inválidas se rechazan.
- No se convierten ni archivan automáticamente emisiones antiguas. No se marcan emisiones al descargar o previsualizar.

## Fallos y operación

El identificador de emisión y la restricción única alumno/curso impiden duplicar la emisión. Antes de copiar una firma se rechazan archivos previos para ese identificador. Si falla el render o archivo, se revierte la transacción y solo se eliminan los archivos creados en ese intento, nunca los originales previos.

MySQL y el filesystem no comparten una transacción distribuida. Un cierre abrupto del proceso o un error incierto al confirmar puede dejar archivos huérfanos; no se borran ni sobrescriben automáticamente. Una colisión posterior se bloquea y exige revisión administrativa. Deben respaldarse juntos la base, los PDF privados y las firmas. No se promete recuperar el PDF original de una emisión antigua que nunca se archivó. Archivos externos o guardados en otras ubicaciones requieren una asociación verificada antes de incorporarse; no se adivinan rutas.

## Verificación aislada sin migraciones

`php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit --bootstrap tests/estado-personas-bootstrap.php --do-not-cache-result tests/Feature/BoletaEstructuraConductaTest.php`

`php -d memory_limit=1024M tests/Support/generar-boleta-estructura.php`

Para las siete variantes de producción y la referencia de trece materias: agregar `--produccion`. La selección incluye Windows/Word con MB/P, Inglés, materias sin calificación, B/MB/E/P/vacío, materias sin correspondencia y una tabla extensa.

Para la escala: agregar `tests/Feature/ConductaEscalaTest.php` a la selección de PHPUnit. Verifica el controlador docente real con datos sintéticos en SQLite en memoria: valores permitidos, rechazo de códigos ajenos, persistencia de calificaciones, representación del selector y etiquetas PDF. No ejecuta migraciones.

Agregar `tests/Feature/BoletaDocumentosTest.php` para mediana, snapshots, emisión nueva, archivo exclusivo, fallos y permisos. Generar las ocho muestras de consolidación con `php -d memory_limit=1024M tests/Support/generar-boleta-estructura.php --consolidacion`.

`php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit --bootstrap tests/estado-personas-bootstrap.php --do-not-cache-result tests/Feature/BoletaEstructuraConductaTest.php tests/Feature/BoletaPdfProduccionTest.php`

Se utiliza SQLite en memoria y almacenamiento temporal. Las pruebas de estructura y el generador rechazan cualquier consulta y emplean modelos no guardados. La prueba de producción construye un esquema mínimo desechable directamente en SQLite para verificar el controlador y las consultas reales de elegibilidad, sin ejecutar migraciones. El generador deja PDF y HTML privados en una carpeta temporal, incluida una muestra extensa para revisar paginación. No se emiten documentos históricos.

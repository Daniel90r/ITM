# Visualización institucional de reportes PDF

## Diagnóstico y solución

Alumnos inscritos, Cursos, Mensualidades, Maestros y Solicitudes descargan
mediante `ReporteController`; Asistencias utiliza `AsistenciaController::pdf`.
Las seis rutas devuelven `application/pdf` con `Content-Disposition: attachment`
y `Cache-Control: private, no-store`. Cuando el usuario abre esos archivos o
URLs directamente, la interfaz pertenece al visor PDF del navegador. No hay
un encabezado HTML de la aplicación que pueda imponer su favicon. Esto es
distinto del favicon institucional de las páginas, que ya funciona.

Se añade «Visualizar PDF» sin retirar ni cambiar «Descargar PDF». La nueva
URL `/admin/reportes/{tipo}/visor` admite exclusivamente `alumnos`, `cursos`,
`asistencias`, `mensualidades`, `maestros` e `inscripciones`. Usa `auth` y
`role:admin`, título descriptivo y el componente de favicon ya existente.
El tipo `inscripciones` corresponde a Solicitudes, no a Alumnos inscritos.

La página solicita una sola vez la ruta PDF original con las credenciales
de la sesión. No duplica consultas, cálculos, validaciones ni generación.
Verifica respuesta HTTP, MIME y firma `%PDF-`; una redirección a login o una
respuesta HTML no se incrusta como si fuera PDF. Los filtros inválidos o la
relación curso–materia incorrecta siguen siendo rechazados por el controlador
PDF original; el visor muestra un mensaje para volver y corregirlos.

El PDF se mantiene como Blob en memoria del navegador, sin reescribirlo.
El iframe y «Descargar PDF» utilizan esa misma copia y conservan el nombre
de descarga de la respuesta original. No se crea ningún documento público,
archivo temporal del servidor, caché persistente de la aplicación ni nueva
ruta para leer archivos privados. Volver conserva los filtros. Cursos y
Asistencias deshabilitan también Visualizar si hay cambios sin aplicar.

Las rutas de descarga anteriores conservan URL, validaciones, encabezados,
filtros y contenido. El mapa de reportes es fijo: no acepta URL externas,
paths de disco, identificadores de documentos históricos ni redirecciones
proporcionadas por el usuario. El HTML es privado/no-store y limita conexiones,
scripts y contenido incrustado mediante CSP; impide incrustación externa.
La copia Blob se revoca al abandonar la página. Al recuperar una página de
la caché de navegación se recarga para comprobar de nuevo la sesión.

## Comprobación manual

1. Iniciar sesión como administrador.
2. Abrir el reporte, aplicar los filtros y pulsar **Visualizar PDF**.
3. Confirmar que la dirección termina en `/visor` (más filtros), el título
   identifica el reporte y la pestaña muestra el logotipo ITM.
4. Revisar el PDF incrustado; utilizar Descargar PDF, Imprimir y Volver al reporte.
5. Comprobar que Volver conserva los filtros y que la descarga sigue disponible
   directamente desde el listado. Repetir en Solicitudes.
6. Probar sin sesión y con roles docente/alumno: el visor no debe ser accesible.

Si se abre el PDF directamente o desde el disco, puede continuar apareciendo
el icono genérico: la solución institucional se aplica a la pestaña HTML del
visor, no modifica el visor del navegador. Su implementación se puede consultar
en el [código del visor de Chromium](https://chromium.googlesource.com/chromium/src/%2B/refs/heads/main/chrome/browser/resources/pdf/).
Si persiste un icono antiguo en una pestaña HTML, recargar esa página; los
enlaces del favicon conservan su versión por hash.

Imprimir invoca la impresión del iframe mediante el navegador. Si este la
impide, se indica utilizar la barra nativa del documento o descargarlo.
Algunos móviles o políticas de navegador pueden impedir incrustar PDF: la
descarga permanece como alternativa. JavaScript desactivado permite descarga
directa, pero no incrustación. No se incorpora una biblioteca PDF adicional.

## Pruebas aisladas

```powershell
$env:ITM_EXPORT_VISOR_FIXTURES='1'
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php tests/Feature/ReporteVisorTest.php --do-not-cache-result
node tests/Frontend/reportes-visor.cjs
```

SQLite en memoria y almacenamiento temporal. Exportación opcional exclusivamente
sintética en `storage/app/reportes-referencia/visor`, fuera del disco público.
El servidor de prueba solo sirve esos fixtures y assets enumerados; no inicia
WAMP ni Laravel con la base real.

La prueba específica aprobó 4 pruebas con 237 aserciones: roles, seis tipos,
títulos, favicon, filtros, seguridad de las URL, rutas PDF originales,
curso–materia, validaciones, descargas y ausencia de generación en la página HTML.

Regresión en una sola ejecución: **92 pruebas aprobadas, 14.514 aserciones,
sin fallos**, incluyendo las cuatro pruebas anteriores (no se suman de nuevo).
Filtro utilizado con el mismo bootstrap y `--do-not-cache-result`:
`ReporteVisorTest|InstitutionalFaviconTest|ReportesAdministrativosTest|ReporteAlumnosPdfTest|ReporteCursosPdfTest|ReporteInscripcionesPdfTest|ReporteMensualidadesPdfTest|ReporteMaestrosPdfTest|ControlAsistenciaPdfTest|AsistenciasPeriodoTest|StaffResponsiveTest`.

Chrome 155.0.8059.85 y Edge 155.0.4283.62, en modo headless y perfiles nuevos:
**60 combinaciones** (6 reportes × 5 anchos × 2 navegadores) y **8 comprobaciones
de errores** sin fallos. Anchos: 320, 375, 390, 768 y 1440 px. Se verifica
favicon decodificable, títulos, ausencia de desbordamiento, carga autenticada
simulada del PDF, un único pedido de generación y descarga sin otra generación.
El SHA-256 del archivo descargado por el navegador coincide con el PDF original
de cada fixture. Se prueban 403, 422, redirección a login y HTML que no es PDF.
Los permisos reales de Laravel se prueban en PHP, no en el servidor simulado.

Capturas privadas de escritorio/móvil y resultados JSON en
`storage/app/reportes-referencia/visor`. La automatización comprueba el favicon
de la página y su carga; no muestra físicamente la barra de pestañas de Chrome
o Edge. Las pruebas del botón de impresión no imprimen en una impresora real.
No se verificaron dispositivos físicos, Safari ni Firefox. Los PDF grandes
requieren memoria del navegador para conservar su copia; no hay persistencia
adicional del documento en el servidor.

## Archivos del ajuste

- `app/Http/Controllers/Admin/ReporteVisorController.php` (nuevo)
- `routes/admin.php`
- `resources/views/admin/reportes/visor.blade.php` (nuevo)
- `resources/views/components/reportes-visualizar-pdf.blade.php` (nuevo)
- `resources/views/components/reportes-descargar-pdf.blade.php`
- `resources/views/admin/reportes/partials/contenido.blade.php`
- `resources/views/admin/reportes/cursos.blade.php`
- `resources/views/admin/reportes/asistencias.blade.php`
- `resources/views/admin/solicitudes.blade.php`
- `public/css/reportes-visor.css` (nuevo)
- `public/js/reportes-visor.js` (nuevo)
- `tests/Feature/ReporteVisorTest.php` (nuevo)
- `tests/Frontend/reportes-visor.cjs` (nuevo)
- `docs/visor-reportes-pdf.md` (nuevo)
- `docs/favicon-institucional.md` (referencia al visor nuevo)

No se modifican los servicios de datos, controladores de generación PDF,
plantillas PDF, base de datos, documentos históricos, Apache ni procesos PHP.

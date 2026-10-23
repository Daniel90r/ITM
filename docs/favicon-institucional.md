# Favicon institucional ITM

Actualización: ya existe una [página de visualización institucional protegida](visor-reportes-pdf.md)
para los seis reportes. Esta permite mostrar el favicon en la pestaña HTML;
se mantiene la limitación del PDF abierto directamente descrita a continuación.

## Inventario y cambio

El logotipo oficial del menú lateral, inicio de sesión y paneles es
`public/images/logo_itm.jpg` (624 × 624). Se conserva intacto. El archivo
`public/images/logo_itm.png` contiene los mismos bytes JPEG y no se reutiliza
como PNG. Antes del ajuste, los layouts declaraban el JPEG con MIME PNG,
`public/favicon.ico` estaba vacío y el login no declaraba favicon.

`resources/views/components/institutional-favicon.blade.php` centraliza ICO
multirresolución (16/32/48), PNG 32 y 192, y Apple touch icon 180. Lo utilizan
los layouts app (administrador, docente, alumno y todos los reportes), guest
y el login independiente. La portada welcome de Laravel no está publicada:
la ruta raíz redirige al login. No se modificaron títulos, rutas, permisos,
filtros, enlaces de descarga ni plantillas PDF.

Los iconos estáticos se obtienen con
`php tools/generate-institutional-favicon.php` usando GD. El script no inicia
Laravel ni consulta la base de datos. Reduce el logotipo completo, manteniendo
su proporción y centrando sobre blanco si el recurso cambia de proporción.
No recorta ni altera el original. Los enlaces usan `asset()` para respetar la
URL base y un identificador SHA-256 del ICO para renovar la caché al regenerar.
La petición automática a `/favicon.ico` depende de la raíz del dominio; en una
instalación bajo `/ITM/public` las páginas usan sus enlaces explícitos correctos.

## PDF abierto directamente

Una respuesta `application/pdf` no tiene un encabezado HTML donde aplicar el
componente. El visor integrado de Chrome/Edge controla su interfaz y puede
mostrar un icono genérico aunque el sitio tenga favicon. El ICO está disponible
como alternativa para navegadores que lo consulten, sin garantizar ese uso.
No se insertan metadatos HTML en los PDF ni se cambian sus respuestas.

Una solución alternativa sería una página HTML protegida con favicon que
incruste el PDF protegido sin modificarlo; cambiaría la navegación, y no forma
parte de este ajuste. Tampoco se promete controlar las pestañas de archivos
PDF descargados y abiertos desde el disco local.

Referencias: [iconos y Apple touch icon en MDN](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/rel),
[metadatos y favicon de raíz en MDN](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/Structuring_content/Webpage_metadata),
[visor PDF de Chromium](https://chromium.googlesource.com/chromium/src/%2B/main/pdf/ui/).

## Verificación reproducible

Solo SQLite en memoria y almacenamiento temporal:

```powershell
$env:ITM_EXPORT_FAVICON_FIXTURES='1'
php -d xdebug.mode=off vendor/phpunit/phpunit/phpunit --bootstrap tests/documentos-isolated-bootstrap.php tests/Feature/InstitutionalFaviconTest.php --do-not-cache-result
node tests/Frontend/institutional-favicon.cjs
```

La exportación opcional genera exclusivamente metadatos de prueba en
`storage/app/reportes-referencia/favicon`; nunca publica datos de estudiantes.
El servidor HTTP de prueba permite únicamente esos metadatos y los iconos.
También permite un único PDF ficticio generado por Dompdf en la prueba de
exportación, para inspeccionar el visor integrado sin documentos reales.
Los perfiles de navegador son nuevos y están separados de los del usuario.

Prueba específica: 2 pruebas aprobadas, 129 aserciones. Se comprobaron el ICO
y sus tres imágenes, tamaños/MIME de los PNG, enlaces versionados y páginas
de login, recuperación, alumnos inscritos, cursos, asistencias, mensualidades,
maestros, solicitudes y paneles de docente y alumno.

Chrome 155.0.8059.85 y Edge 155.0.4283.62: 80 combinaciones verificadas sin
fallos (10 páginas × 4 anchos × 2 navegadores). Se verificaron respuestas HTTP,
decodificación y dimensiones de cada icono en 320, 390, 768 y 1440 px. Es una
comprobación headless de recursos y metadatos; no verifica la interfaz de la
pestaña visible ni el icono de la aplicación al instalarla en un móvil.
Safari/iOS, Firefox y dispositivos físicos no se verificaron.
El PDF ficticio de Dompdf se abrió correctamente en ambos navegadores como
`application/pdf`; los documentos directos no tenían enlaces HTML de iconos.
Esto verifica la apertura y confirma que el componente HTML no controla esas
pestañas, pero no certifica qué icono pinta la interfaz visible del navegador.

Regresión ejecutada en una sola invocación (incluye las dos pruebas de favicon):
88 pruebas aprobadas, 14.277 aserciones, sin fallos. Selección:
`InstitutionalFaviconTest|ReportesAdministrativosTest|ReporteAlumnosPdfTest|ReporteCursosPdfTest|ReporteInscripcionesPdfTest|ReporteMensualidadesPdfTest|ReporteMaestrosPdfTest|ControlAsistenciaPdfTest|AsistenciasPeriodoTest|StaffResponsiveTest`.
Se usó `--bootstrap tests/documentos-isolated-bootstrap.php --do-not-cache-result`;
base SQLite en memoria y almacenamiento temporal. Los resultados de la ejecución
específica no se suman a los de regresión.

## Archivos de este ajuste

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/components/institutional-favicon.blade.php`
- `public/favicon.ico`
- `public/favicons/itm-32.png`
- `public/favicons/itm-180.png`
- `public/favicons/itm-192.png`
- `tools/generate-institutional-favicon.php`
- `tests/Feature/InstitutionalFaviconTest.php`
- `tests/Frontend/institutional-favicon.cjs`
- `docs/favicon-institucional.md`

Las cachés de favicons del navegador pueden persistir: los enlaces versionados
evitan reutilizar el recurso anterior; una pestaña abierta o el favicon de raíz
pueden requerir recarga o limpieza puntual de caché del sitio. No se modifica
la caché global de los usuarios.

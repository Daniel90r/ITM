<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} — ITM Aguilares</title>
    <x-institutional-favicon />
    <link rel="stylesheet" href="{{ asset('css/reportes-visor.css') }}?v=1">
    <script src="{{ asset('js/reportes-visor.js') }}?v=1" defer></script>
</head>
<body>
    <header class="visor-header">
        <img src="{{ asset('images/logo_itm.jpg') }}" alt="Logotipo oficial del ITM" width="56" height="56">
        <div><p>ITM Aguilares · Reportes institucionales</p><h1>{{ $titulo }}</h1></div>
    </header>
    <main id="visor-reporte" data-pdf-url="{{ $pdfUrl }}">
        <nav class="visor-actions" aria-label="Acciones del reporte">
            <a class="button secondary" href="{{ $volverUrl }}">← Volver al reporte</a>
            <a class="button primary" id="descargar-pdf" href="{{ $pdfUrl }}">Descargar PDF</a>
            <button class="button secondary" id="imprimir-pdf" type="button" disabled>Imprimir</button>
        </nav>
        <p id="visor-status" role="status" aria-live="polite">Cargando el PDF con los filtros seleccionados…</p>
        <iframe id="documento-pdf" title="{{ $titulo }}: documento PDF" hidden></iframe>
        <p class="visor-help">Si tu navegador no permite visualizar o imprimir aquí, descarga el PDF y utiliza su visor. También puedes imprimir desde la barra del documento.</p>
        <noscript><p>Para visualizar aquí, activa JavaScript. Puedes descargar el documento mediante el botón Descargar PDF.</p></noscript>
    </main>
</body>
</html>

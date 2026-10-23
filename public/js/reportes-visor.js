(() => {
    'use strict';
    const root = document.getElementById('visor-reporte');
    if (!root) return;
    const frame = document.getElementById('documento-pdf');
    const download = document.getElementById('descargar-pdf');
    const print = document.getElementById('imprimir-pdf');
    const status = document.getElementById('visor-status');
    download.removeAttribute('href');
    download.setAttribute('aria-disabled', 'true');
    download.tabIndex = -1;
    const controller = new AbortController();
    let blobUrl;
    const timeout = setTimeout(() => controller.abort(), 120000);
    (async () => {
        try {
            const url = new URL(root.dataset.pdfUrl, location.href);
            if (url.origin !== location.origin) throw new Error('origen');
            const response = await fetch(url.href, {credentials:'same-origin', cache:'no-store', redirect:'error', signal:controller.signal});
            if (!response.ok) {
                if ([401, 403].includes(response.status)) throw new Error('permiso');
                if ([400, 422].includes(response.status)) throw new Error('filtros');
                throw new Error('respuesta');
            }
            if (!(response.headers.get('Content-Type') || '').toLowerCase().startsWith('application/pdf')) throw new Error('respuesta');
            const bytes = await response.arrayBuffer();
            if (new TextDecoder().decode(bytes.slice(0, 5)) !== '%PDF-') throw new Error('respuesta');
            blobUrl = URL.createObjectURL(new Blob([bytes], {type:'application/pdf'}));
            const disposition = response.headers.get('Content-Disposition') || '';
            const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i);
            const plainName = disposition.match(/filename="([^"\r\n]+)"|filename=([^;\r\n]+)/i);
            let filename = 'reporte-itm.pdf';
            try {filename = encodedName ? decodeURIComponent(encodedName[1]) : (plainName?.[1] || plainName?.[2] || filename);} catch {}
            download.download = filename.replace(/[\\/\x00-\x1f]/g, '_').trim();
            download.href = blobUrl;
            frame.addEventListener('load', () => {
                print.disabled = false;
                status.textContent = 'PDF preparado con los filtros seleccionados.';
            }, {once:true});
            frame.src = blobUrl;
            frame.hidden = false;
        } catch (error) {
            status.textContent = error.message === 'filtros'
                ? 'No se pudo generar el PDF: vuelve al reporte y revisa los filtros.'
                : error.message === 'permiso'
                    ? 'No tienes autorización para descargar este reporte.'
                    : 'No se pudo cargar el PDF. Comprueba tu sesión y vuelve al reporte para intentarlo nuevamente.';
        } finally {
            clearTimeout(timeout);
            if (!blobUrl) download.href = root.dataset.pdfUrl;
            download.removeAttribute('aria-disabled');
            download.removeAttribute('tabindex');
        }
    })();
    print.addEventListener('click', () => {
        try {frame.contentWindow.focus();frame.contentWindow.print();}
        catch {status.textContent = 'Este navegador no permite imprimir desde el botón. Utiliza Imprimir en la barra del documento o descarga el PDF.';}
    });
    window.addEventListener('pagehide', () => {controller.abort();if (blobUrl) URL.revokeObjectURL(blobUrl);});
    window.addEventListener('pageshow', event => {if (event.persisted) location.reload();});
})();

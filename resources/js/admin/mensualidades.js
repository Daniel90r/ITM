function abrirModalGenerar() {
    document.getElementById('modalGenerar').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function cerrarModalGenerar() {
    document.getElementById('modalGenerar').style.display = 'none';
    document.body.style.overflow = '';
    window.toggleAlumno('todos');
    document.getElementById('filtroCursoModal').value = '';
    document.getElementById('selectCursoLote').value = '';
    filtrarAlumnosModal();
}
function toggleAlumno(tipo) {
    for (const [valor, radio, boton] of [['todos','radioTodos','btnTodos'],['curso','radioCurso','btnCurso'],['uno','radioUno','btnUno']]) {
        const activo = tipo === valor;
        document.getElementById(radio).checked = activo;
        const btn = document.getElementById(boton);
        btn.style.borderColor = activo ? '#111827' : '#e5e7eb';
        btn.style.background = activo ? '#f3f4f6' : '#ffffff';
        btn.style.color = activo ? '#111827' : '#6b7280';
    }
    document.getElementById('selectCursoDiv').style.display = tipo === 'curso' ? 'block' : 'none';
    document.getElementById('selectAlumnoDiv').style.display = tipo === 'uno' ? 'block' : 'none';
    document.getElementById('selectCursoLote').required = tipo === 'curso';
    document.getElementById('selectAlumno').required = tipo === 'uno';
}
function filtrarAlumnosModal() {
    const cursoId = document.getElementById('filtroCursoModal').value;
    const select = document.getElementById('selectAlumno');
    let visibles = 0;
    select.value = '';
    select.querySelectorAll('option[data-curso]').forEach(opt => {
        const visible = !cursoId || opt.dataset.curso === cursoId;
        opt.style.display = visible ? '' : 'none';
        if (visible) visibles++;
    });
    select.querySelector('option[value=""]').textContent = !cursoId ? '— Selecciona un curso primero —' : (visibles ? '— Seleccionar alumno —' : '— Sin alumnos en este curso —');
    document.getElementById('sinAlumnosMsg').style.display = cursoId && !visibles ? 'block' : 'none';
}
let temporizadorBusqueda;
function filtrarMensualidades() {
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(() => document.getElementById('mensualidadesFiltros').requestSubmit(), 400);
}
function filaVisible(chk) {
    const fila = chk.closest('[data-mensualidad-id]');
    return !!fila && fila.offsetParent !== null;
}
// La misma mensualidad puede verse bajo dos cursos del alumno: contarla una sola vez.
export function seleccionUnica(checks) {
    return [...new Map(Array.from(checks).filter(c => c.checked && filaVisible(c)).map(c => [c.value, c])).values()];
}
function actualizarBarraLote() {
    const barra = document.getElementById('formLote');
    if (!barra) return;
    const marcadas = seleccionUnica(document.querySelectorAll('.chk-mensualidad'));
    barra.style.display = marcadas.length ? 'flex' : 'none';
    document.getElementById('loteCantidad').textContent = marcadas.length;
    document.getElementById('loteTotal').textContent = '$' + marcadas.reduce((s, c) => s + Number(c.dataset.monto), 0).toFixed(2);
    document.querySelectorAll('.chk-curso').forEach(master => {
        const checks = Array.from(document.querySelectorAll('.chk-mensualidad')).filter(c => c.dataset.curso === master.dataset.curso && filaVisible(c));
        master.checked = checks.length > 0 && checks.every(c => c.checked);
        master.indeterminate = !master.checked && checks.some(c => c.checked);
    });
}
function limpiarSeleccionLote() {
    document.querySelectorAll('.chk-mensualidad, .chk-curso').forEach(c => c.checked = false);
    actualizarBarraLote();
}
function desmarcarPanel(panel) {
    panel.querySelectorAll('.chk-mensualidad').forEach(c => c.checked = false);
    actualizarBarraLote();
}
export function prepararCobroLote(event) {
    event.preventDefault();
    document.querySelectorAll('.chk-mensualidad').forEach(c => { if (!filaVisible(c)) c.checked = false; });
    const marcadas = seleccionUnica(document.querySelectorAll('.chk-mensualidad'));
    if (!marcadas.length) {
        window.mostrarNotificacion('No hay mensualidades seleccionadas.', 'aviso');
        return false;
    }
    const total = marcadas.reduce((s, c) => s + Number(c.dataset.monto), 0);
    window.mostrarConfirmacion({ mensaje: '¿Registrar ' + marcadas.length + ' pago(s) por $' + total.toFixed(2) + '?', titulo: 'Cobro en lote', tipo: 'exito', boton: 'Sí, cobrar' }).then(result => {
        if (!result.isConfirmed) return;
        const ids = new Set();
        document.querySelectorAll('.chk-mensualidad:checked').forEach(c => {
            if (!filaVisible(c) || ids.has(c.value)) c.checked = false;
            else ids.add(c.value);
        });
        document.getElementById('formLote').submit();
    });
    return false;
}

export function initMensualidades(root) {
    if (!root || root.dataset.inicializado) return;
    root.dataset.inicializado = 'true';
    async function cargar(panel, url) {
        if (panel.dataset.cargando === 'true') return;
        desmarcarPanel(panel);
        panel.dataset.cargando = 'true'; panel.dataset.urlActual = url;
        panel.setAttribute('aria-busy', 'true');
        panel.innerHTML = '<p class="mp-detail-note" role="status">Cargando mensualidades…</p>';
        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw new Error([401,403].includes(response.status) || response.redirected ? 'sesion' : 'carga');
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('carga');
            panel.innerHTML = data.html;
            panel.dataset.cargado = 'true';
            window.initConfirmaciones?.();
        } catch (error) {
            panel.dataset.cargado = 'false';
            const mensaje = document.createElement('p');
            mensaje.className = 'mp-detail-note'; mensaje.setAttribute('role', 'alert');
            mensaje.textContent = error.message === 'sesion' ? 'Tu sesión expiró o no tienes permiso. Vuelve a iniciar sesión.' : 'No fue posible cargar las mensualidades. Inténtalo de nuevo.';
            const retry = document.createElement('button');
            retry.type = 'button'; retry.className = 'mp-btn'; retry.dataset.mensualidadesReintentar = 'true'; retry.textContent = 'Reintentar';
            panel.replaceChildren(mensaje, retry);
        } finally {
            panel.dataset.cargando = 'false'; panel.setAttribute('aria-busy', 'false'); actualizarBarraLote();
        }
    }
    root.addEventListener('click', event => {
        const boton = event.target.closest('[data-ver-mensualidades]');
        if (boton) {
            const detalle = document.getElementById(boton.getAttribute('aria-controls'));
            const panel = detalle.querySelector('[data-mensualidades-detalle]');
            const expandir = boton.getAttribute('aria-expanded') !== 'true';
            detalle.hidden = !expandir; boton.setAttribute('aria-expanded', String(expandir));
            boton.textContent = expandir ? 'Ocultar mensualidades' : 'Ver mensualidades';
            if (!expandir) desmarcarPanel(panel);
            else if (panel.dataset.cargado !== 'true') cargar(panel, panel.dataset.urlActual || boton.dataset.url);
            return;
        }
        const enlace = event.target.closest('[data-mensualidad-paginacion] a');
        if (enlace) { event.preventDefault(); cargar(enlace.closest('[data-mensualidades-detalle]'), enlace.href); return; }
        const retry = event.target.closest('[data-mensualidades-reintentar]');
        if (retry) { const panel = retry.closest('[data-mensualidades-detalle]'); cargar(panel, panel.dataset.urlActual); }
    });
    root.addEventListener('change', event => {
        if (event.target.classList.contains('chk-curso')) {
            document.querySelectorAll('.chk-mensualidad').forEach(c => { if (c.dataset.curso === event.target.dataset.curso && filaVisible(c)) c.checked = event.target.checked; });
        }
        if (event.target.classList.contains('chk-mensualidad') || event.target.classList.contains('chk-curso')) actualizarBarraLote();
    });
}
if (typeof window !== 'undefined') {
    Object.assign(window, { abrirModalGenerar, cerrarModalGenerar, toggleAlumno, filtrarAlumnosModal, filtrarMensualidades, prepararCobroLote, limpiarSeleccionLote });
    document.addEventListener('DOMContentLoaded', () => {
        initMensualidades(document.querySelector('[data-mensualidades-panel]'));
        document.getElementById('modalGenerar')?.addEventListener('click', function(event) { if (event.target === this) cerrarModalGenerar(); });
        document.getElementById('mensualidadesFiltros')?.addEventListener('submit', () => clearTimeout(temporizadorBusqueda));
    });
}

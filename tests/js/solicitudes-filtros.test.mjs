import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';

const source = readFileSync(new URL('../../resources/js/admin/solicitudes.js', import.meta.url), 'utf8');
function entorno() {
    let envios = 0; let pendiente; let cancelados = 0;
    const estado = { value: 'todos' };
    const form = { requestSubmit() { envios++; } };
    const contexto = {
        window: {},
        document: { getElementById: id => ({ filtrosSolicitudes: form, estadoSolicitud: estado }[id]), addEventListener() {} },
        clearTimeout() { pendiente = undefined; cancelados++; },
        setTimeout(fn, ms) { assert.equal(ms, 400); pendiente = fn; return 1; },
    };
    runInNewContext(source, contexto);
    return { ...contexto.window, estado, envios: () => envios, pendiente: () => pendiente, cancelados: () => cancelados };
}

for (const estado of ['todos', 'rechazada', 'aprobada', 'pendiente', 'inactiva']) {
    test(`${estado} consulta al servidor sin ocultar solo filas de la página`, () => {
        const e = entorno(); e.setFiltroEstado(estado);
        assert.equal(e.estado.value, estado); assert.equal(e.envios(), 1);
    });
}
test('la búsqueda espera y cancela solicitudes intermedias', () => {
    const e = entorno(); e.filtrarSolicitudes(); e.filtrarSolicitudes();
    assert.equal(e.envios(), 0); assert.equal(e.cancelados(), 2);
    e.pendiente()(); assert.equal(e.envios(), 1);
});
test('cambiar estado cancela la búsqueda pendiente para no duplicar envíos', () => {
    const e = entorno(); e.filtrarSolicitudes(); e.setFiltroEstado('rechazada');
    assert.equal(e.pendiente(), undefined); assert.equal(e.envios(), 1);
});

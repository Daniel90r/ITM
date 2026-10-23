const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/reportes/asistencias.blade.php'), 'utf8');
const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1];

function pagina(conDescarga = true) {
    const elementos = {};
    for (const id of ['filtros-asistencia', 'curso-asistencia', 'materia-asistencia', 'descarga-asistencia', 'visualizar-asistencia', 'filtros-pendientes', 'modalidad-asistencia', 'campo-mes-asistencia', 'campo-anio-asistencia', 'mes-asistencia', 'anio-asistencia']) {
        elementos[id] = {
            eventos: {}, atributos: {}, value: '123', disabled: false, hidden: true,
            addEventListener(nombre, accion) { this.eventos[nombre] = accion; },
            setAttribute(nombre, valor) { this.atributos[nombre] = valor; },
            removeAttribute(nombre) { delete this.atributos[nombre]; },
            requestSubmit() { this.enviado = true; },
        };
    }
    elementos['modalidad-asistencia'].value = 'mensual';
    elementos['descarga-asistencia'].atributos.href = '/admin/asistencias/pdf?curso_id=1';
    elementos['visualizar-asistencia'].atributos.href = '/admin/reportes/asistencias/visor?curso_id=1';
    elementos['filtros-asistencia'].querySelectorAll = selector => {
        assert.equal(selector, '.reportes-preview');
        return conDescarga ? [elementos['visualizar-asistencia']] : [];
    };
    if (!conDescarga) {
        elementos['descarga-asistencia'] = null;
        elementos['visualizar-asistencia'] = null;
    }
    vm.runInNewContext(script, { document: { getElementById: id => elementos[id] } });
    return elementos;
}

test('cambiar curso limpia la materia anterior y solicita inmediatamente las opciones reales', () => {
    const e = pagina();
    e['curso-asistencia'].eventos.change();
    assert.equal(e['materia-asistencia'].value, '');
    assert.equal(e['materia-asistencia'].disabled, true);
    assert.equal(e['filtros-asistencia'].enviado, true);
    assert.equal(e['filtros-asistencia'].atributos['aria-busy'], 'true');
});

test('editar filtros impide descargar resultados anteriores a los cambios', () => {
    for (const evento of ['input', 'change']) {
        const e = pagina();
        assert.equal(e['descarga-asistencia'].atributos.href, '/admin/asistencias/pdf?curso_id=1');
        assert.equal(e['visualizar-asistencia'].atributos.href, '/admin/reportes/asistencias/visor?curso_id=1');
        assert.equal(e['descarga-asistencia'].atributos['aria-disabled'], undefined);
        assert.equal(e['visualizar-asistencia'].atributos['aria-disabled'], undefined);
        e['filtros-asistencia'].eventos[evento]();
        assert.equal(e['descarga-asistencia'].atributos.href, undefined);
        assert.equal(e['descarga-asistencia'].atributos['aria-disabled'], 'true');
        assert.equal(e['visualizar-asistencia'].atributos.href, undefined);
        assert.equal(e['visualizar-asistencia'].atributos['aria-disabled'], 'true');
        assert.equal(e['filtros-pendientes'].hidden, false);
    }
});

test('la actualización funciona también cuando todavía no existe enlace de descarga', () => {
    const e = pagina(false);
    assert.doesNotThrow(() => e['filtros-asistencia'].eventos.input());
    e['curso-asistencia'].eventos.change();
    assert.equal(e['filtros-asistencia'].enviado, true);
});


test('modalidad mensual o anual conserva el año y alterna el mes', () => {
    const e = pagina();
    for (const modalidad of ['mensual', 'anual']) {
        e['modalidad-asistencia'].value = modalidad;
        e['modalidad-asistencia'].eventos.change();
        assert.equal(e['mes-asistencia'].disabled, modalidad !== 'mensual');
        assert.equal(e['anio-asistencia'].disabled, false);
        assert.equal(e['campo-mes-asistencia'].hidden, modalidad !== 'mensual');
    }
});

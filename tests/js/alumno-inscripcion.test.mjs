import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/alumno/inscripcion.js', import.meta.url), 'utf8');

function intentarEnvio({ bloqueado = false, firmaAlumno = 'firma', firmaEncargado = 'firma', curso = true, valido = true } = {}) {
    let enviados = 0;
    let validaciones = 0;
    const avisos = [];
    const formulario = {
        dataset: { encargadoBloqueado: bloqueado ? '1' : '0' },
        reportValidity() { validaciones++; return valido; },
        submit() { enviados++; },
    };
    const elementos = {
        formInscripcion: formulario,
        firmaAlumnoData: { value: firmaAlumno },
        firmaEncargadoData: firmaEncargado === null ? null : { value: firmaEncargado },
    };
    const contexto = {
        window: {},
        document: {
            getElementById: id => elementos[id],
            querySelector: () => curso ? {} : null,
            addEventListener() {},
        },
        alert: mensaje => avisos.push(mensaje),
    };
    runInNewContext(source, contexto);
    contexto.window.enviarSolicitud();

    return { enviados, validaciones, avisos };
}

test('el bloqueo oficial impide enviar incluso invocando directamente la función', () => {
    assert.deepEqual(intentarEnvio({ bloqueado: true }), { enviados: 0, validaciones: 0, avisos: [] });
});

test('un menor con ambas firmas y curso puede enviar', () => {
    assert.deepEqual(intentarEnvio(), { enviados: 1, validaciones: 1, avisos: [] });
});

test('un adulto puede enviar sin campo de firma de encargado', () => {
    assert.deepEqual(intentarEnvio({ firmaEncargado: null }), { enviados: 1, validaciones: 1, avisos: [] });
});

for (const [caso, opciones] of [
    ['sin firma del alumno', { firmaAlumno: '' }],
    ['sin firma del encargado', { firmaEncargado: '' }],
    ['sin sección seleccionada', { curso: false }],
]) {
    test(`impide enviar ${caso}`, () => {
        const resultado = intentarEnvio(opciones);
        assert.equal(resultado.enviados, 0);
        assert.equal(resultado.avisos.length, 1);
    });
}

test('respeta la validación nativa antes de enviar', () => {
    assert.deepEqual(intentarEnvio({ valido: false }), { enviados: 0, validaciones: 1, avisos: [] });
});

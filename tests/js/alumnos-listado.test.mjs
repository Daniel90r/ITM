import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

test('buscar, filtrar por curso y año y limpiar conservan el orden del servidor', () => {
    const filas = [
        { id: 4, dataset: { nombre: 'ana reciente', curso: 'computación', anio: '2026' }, style: {} },
        { id: 3, dataset: { nombre: 'ana intermedia', curso: 'inglés', anio: '2026' }, style: {} },
        { id: 2, dataset: { nombre: 'ana anterior', curso: 'computación', anio: '2026' }, style: {} },
        { id: 1, dataset: { nombre: 'ana antigua', curso: 'computación', anio: '2025' }, style: {} },
    ];
    const elementos = Object.fromEntries(['buscarAlumno', 'filtrarCurso', 'filtrarAnio', 'sinResultados', 'contadorResultados', 'contadorHeader']
        .map(id => [id, { value: '', style: {}, dataset: { total: '4 alumnos inscritos' } }]));
    const context = vm.createContext({ window: {}, document: {
        getElementById: id => elementos[id], querySelectorAll: () => filas, addEventListener() {},
    } });
    vm.runInContext(readFileSync(new URL('../../resources/js/admin/alumnos.js', import.meta.url), 'utf8'), context);
    const visibles = () => filas.filter(f => f.style.display !== 'none').map(f => f.id);
    elementos.buscarAlumno.value = ' Ana ';
    elementos.filtrarCurso.value = 'computación';
    elementos.filtrarAnio.value = '2026';
    context.window.filtrarAlumnos();
    assert.deepEqual(visibles(), [4, 2]);
    assert.equal(elementos.contadorResultados.textContent, '2 alumno(s) encontrado(s)');
    elementos.buscarAlumno.value = 'nadie';
    context.window.filtrarAlumnos();
    assert.deepEqual(visibles(), []);
    assert.equal(elementos.sinResultados.style.display, 'block');
    context.window.limpiarFiltrosAlumnos();
    assert.deepEqual(visibles(), [4, 3, 2, 1]);
    assert.equal(elementos.contadorHeader.textContent, '4 alumnos inscritos');
});

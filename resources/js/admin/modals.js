// ═══════════════════════════════════════════════════════════════
// FORMATEO DE ENTRADA: DUI (reutilizable)
// ───────────────────────────────────────────────────────────────
// La máscara de TELÉFONO no vive aquí: es responsabilidad única de
// utils/inputMasks.js, que la aplica por delegación a [data-phone-mask]
// y por tanto cubre también los modales que se crean dinámicamente.
// Tenerla aquí además creaba dos fuentes de verdad y riesgo de doble binding.
// ═══════════════════════════════════════════════════════════════
import {formatearValorDui} from '../utils/dui.js';

function formatearDui(e) {
    e.target.value = formatearValorDui(e.target.value);
}

function inicializarFormatoDui(selector) {
    document.querySelectorAll(selector).forEach(input => {
        if (!input.dataset.maskInited) {
            input.dataset.maskInited = 'true';
            input.addEventListener('input', formatearDui);
        }
    });
}

// Modal functions for Alumno
function abrirModalNuevoAlumno() {
    const modal = document.getElementById('modalNuevoAlumno');
    if (modal) {
        modal.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
}

function cerrarModalNuevoAlumno() {
    const modal = document.getElementById('modalNuevoAlumno');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
}

// Modal functions for Maestros
function abrirModalMaestro() {
    const modal = document.getElementById('modalNuevoMaestro');
    if (modal) {
        modal.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
}

function cerrarModalMaestro() {
    const modal = document.getElementById('modalNuevoMaestro');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
}

// Modal functions for Editar Maestro
function abrirModalEditarMaestro(id, nombre, apellido, telefono, dui, codigo, email) {
    const editId = document.getElementById('editIdMaestro');
    const editNombre = document.getElementById('editNombre');
    const editApellido = document.getElementById('editApellido');
    const editTelefono = document.getElementById('editTelefono');
    const editDui = document.getElementById('editDui');
    const editCodigo = document.getElementById('editCodigo');
    const editEmail = document.getElementById('editEmail');
    const password = document.getElementById('editPassword');
    const passwordConfirmation = document.getElementById('editPasswordConfirmation');
    const form = document.getElementById('formEditarMaestro');
    const modal = document.getElementById('modalEditarMaestro');

    if (editId) editId.value = id;
    if (editNombre) editNombre.value = nombre;
    if (editApellido) editApellido.value = apellido;
    if (editTelefono) editTelefono.value = telefono || '';
    if (editDui) editDui.value = dui || '';
    if (editCodigo) editCodigo.value = codigo;
    if (editEmail) editEmail.value = email;

    if (password) password.value = '';
    if (passwordConfirmation) passwordConfirmation.value = '';

    if (form) form.action = '/admin/maestros/' + id;
    if (modal) {
        modal.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
}

function cerrarModalEditarMaestro() {
    const modal = document.getElementById('modalEditarMaestro');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
}

// Modal functions for Asignar programa
function abrirModalAsignarCurso() {
    const modal = document.getElementById('modalAsignarCurso');

    if (!modal) {
        return;
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalAsignarCurso() {
    const modal = document.getElementById('modalAsignarCurso');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
    const form = document.getElementById('formAsignarCurso');
    if (form) {
        form.reset();
    }
    olvidarMateriasSeleccionadas();
    // Reset materias container
    const container = document.getElementById('materiasContainer');
    if (container) {
        container.style.display = 'none';
    }
}

// ───────────────────────────────────────────────────────────────
// Lista de materias del modal de asignar.
//
// La lista se construye con createElement/textContent a propósito:
// nombre, código y nombre del maestro docente llegan desde la base de
// datos dentro de la respuesta JSON. Concatenarlos en una cadena y
// asignarla como HTML abría una vía de XSS almacenado.
// ───────────────────────────────────────────────────────────────
function vaciarListaMaterias(list) {
    list.textContent = '';
}

function mensajeMaterias(texto, color) {
    const p = document.createElement('p');
    p.style.fontSize = '12px';
    p.style.color = color;
    p.style.textAlign = 'center';
    p.style.padding = '20px';
    p.textContent = texto;
    return p;
}

function mostrarMensajeMaterias(list, texto, color) {
    vaciarListaMaterias(list);
    list.appendChild(mensajeMaterias(texto, color));
}

// Materias que el administrador había marcado antes de que fallara la
// validación. Sin esto, el modal reabría vacío y había que repetir la
// selección completa a mano.
function leerMateriasSeleccionadas() {
    const modal = document.getElementById('modalAsignarCurso');
    if (!modal) return [];

    try {
        const parsed = modal.dataset.materiasSeleccionadas
            ? JSON.parse(modal.dataset.materiasSeleccionadas)
            : [];

        return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch (error) {
        return [];
    }
}

function olvidarMateriasSeleccionadas() {
    const modal = document.getElementById('modalAsignarCurso');
    if (modal) {
        modal.dataset.materiasSeleccionadas = '[]';
    }
}

function construirFilaMateria(materia, seleccionadas) {
    let estadoTexto;
    let estadoColor;
    let bloqueada = false;

    if (materia.tiene_maestro_actual) {
        estadoTexto = `(Asignada a ${materia.maestro_actual})`;
        estadoColor = '#dc2626';
        bloqueada = true;
    } else if (materia.ya_asignada_a_este_maestro) {
        estadoTexto = '(Ya asignada a este maestro)';
        estadoColor = '#16a34a';
        bloqueada = true;
    } else if (materia.tiene_historial) {
        estadoTexto = '(Vacante - Historial previo)';
        estadoColor = '#16a34a';
    } else {
        estadoTexto = '(Vacante)';
        estadoColor = '#16a34a';
    }

    const label = document.createElement('label');
    label.style.display = 'flex';
    label.style.alignItems = 'center';
    label.style.gap = '8px';
    label.style.padding = '10px 12px';
    label.style.borderRadius = '6px';
    label.style.transition = 'background 0.15s';
    label.style.opacity = bloqueada ? '0.6' : '1';
    label.style.cursor = bloqueada ? 'not-allowed' : 'pointer';
    label.addEventListener('mouseover', function() {
        label.style.background = '#f3f4f6';
    });
    label.addEventListener('mouseout', function() {
        label.style.background = 'transparent';
    });

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.name = 'materia_ids[]';
    checkbox.value = String(materia.id);
    checkbox.id = `materia_${materia.id}`;
    checkbox.disabled = bloqueada;
    checkbox.checked = !bloqueada && seleccionadas.includes(String(materia.id));
    checkbox.style.width = '16px';
    checkbox.style.height = '16px';
    checkbox.style.accentColor = '#111827';
    checkbox.style.cursor = bloqueada ? 'not-allowed' : 'pointer';

    const nombre = document.createElement('span');
    nombre.textContent = `${materia.nombre} — ${materia.codigo}`;
    nombre.style.fontSize = '13px';
    nombre.style.color = bloqueada ? '#9ca3af' : '#111827';
    nombre.style.fontWeight = bloqueada ? '400' : '500';

    const estado = document.createElement('span');
    estado.textContent = estadoTexto;
    estado.style.color = estadoColor;
    estado.style.fontSize = '11px';
    estado.style.marginLeft = '8px';

    label.appendChild(checkbox);
    label.appendChild(nombre);
    label.appendChild(estado);

    return label;
}

// Cargar materias de un curso para el modal de asignar
async function cargarMateriasCurso(cursoId) {
    const container = document.getElementById('materiasContainer');
    const list = document.getElementById('materiasList');
    const btnAsignar = document.getElementById('btnAsignar');

    if (!container || !list) return;

    if (!cursoId) {
        container.style.display = 'none';
        if (btnAsignar) btnAsignar.disabled = true;
        return;
    }

    mostrarMensajeMaterias(list, 'Cargando materias...', '#6b7280');
    container.style.display = 'block';

    try {
        // El backend necesita saber a QUIÉN se está asignando para distinguir
        // "ya es de este maestro" de "pertenece a otro maestro".
        const params = new URLSearchParams();
        const maestroId = document.getElementById('modalAsignarCurso')?.dataset.maestroId;
        if (maestroId) {
            params.set('maestro_id', maestroId);
        }

        const query = params.toString();
        const url = `/admin/maestros/materias-por-curso/${encodeURIComponent(cursoId)}${query ? `?${query}` : ''}`;

        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar materias');
        }

        const data = await response.json();
        const materias = data.materias || [];

        if (materias.length === 0) {
            mostrarMensajeMaterias(list, 'Este curso no tiene materias activas disponibles', '#6b7280');
            if (btnAsignar) btnAsignar.disabled = true;
            return;
        }

        const seleccionadas = leerMateriasSeleccionadas();

        vaciarListaMaterias(list);
        materias.forEach(function(materia) {
            list.appendChild(construirFilaMateria(materia, seleccionadas));
        });

        // Habilitar/deshabilitar el envío según las materias realmente elegibles
        const checkboxes = list.querySelectorAll('input[type="checkbox"]:not(:disabled)');
        if (btnAsignar) {
            btnAsignar.disabled = checkboxes.length === 0;
        }
    } catch (error) {
        console.error('Error cargando materias:', error);
        mostrarMensajeMaterias(list, 'Error al cargar las materias. Intente nuevamente.', '#dc2626');
        if (btnAsignar) btnAsignar.disabled = true;
    }
}

// Inicializar listener para cambio de curso en modal asignar
function inicializarAsignarCursoModal() {
    const cursoSelect = document.getElementById('curso_id');
    if (cursoSelect) {
        cursoSelect.addEventListener('change', function() {
            // La selección anterior pertenecía a otro curso: no tiene sentido arrastrarla.
            olvidarMateriasSeleccionadas();
            cargarMateriasCurso(this.value);
        });

        // Si ya hay un curso seleccionado (error de validación), cargar materias
        if (cursoSelect.value) {
            cargarMateriasCurso(cursoSelect.value);
        }
    }
}

// Modal functions for Cambiar Maestro
function abrirModalCambiarMaestro(ruta, detalleId, cursoNombre, programaNombre) {
    const modal = document.getElementById('modalCambiarMaestro');
    const form = document.getElementById('formCambiarMaestro');

    if (!modal || !form) {
        return;
    }

    form.action = ruta;
    const detalleInput = document.getElementById('detalleIdContexto');
    if (detalleInput) detalleInput.value = detalleId;

    const cursoSpan = document.getElementById('contextoCurso');
    const programaSpan = document.getElementById('contextoPrograma');
    if (cursoSpan) cursoSpan.textContent = cursoNombre;
    if (programaSpan) programaSpan.textContent = programaNombre;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalCambiarMaestro() {
    const modal = document.getElementById('modalCambiarMaestro');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
    const form = document.getElementById('formCambiarMaestro');
    if (form) {
        form.reset();
        form.action = '';
    }
    const detalleInput = document.getElementById('detalleIdContexto');
    if (detalleInput) detalleInput.value = '';
    const cursoSpan = document.getElementById('contextoCurso');
    const programaSpan = document.getElementById('contextoPrograma');
    if (cursoSpan) cursoSpan.textContent = '';
    if (programaSpan) programaSpan.textContent = '';
}

window.abrirModalNuevoAlumno = abrirModalNuevoAlumno;
window.cerrarModalNuevoAlumno = cerrarModalNuevoAlumno;
window.abrirModalMaestro = abrirModalMaestro;
window.cerrarModalMaestro = cerrarModalMaestro;
window.abrirModalEditarMaestro = abrirModalEditarMaestro;
window.cerrarModalEditarMaestro = cerrarModalEditarMaestro;
window.abrirModalAsignarCurso = abrirModalAsignarCurso;
window.cerrarModalAsignarCurso = cerrarModalAsignarCurso;
window.abrirModalCambiarMaestro = abrirModalCambiarMaestro;
window.cerrarModalCambiarMaestro = cerrarModalCambiarMaestro;

document.addEventListener('DOMContentLoaded', function() {
    const modalNuevo = document.getElementById('modalNuevoMaestro');
    if (modalNuevo) {
        modalNuevo.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalMaestro();
        });
    }

    const modalEditar = document.getElementById('modalEditarMaestro');
    if (modalEditar) {
        modalEditar.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalEditarMaestro();
        });
    }

    const modalAsignar = document.getElementById('modalAsignarCurso');
    if (modalAsignar) {
        modalAsignar.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalAsignarCurso();
        });

        if (modalAsignar.dataset.open === '1') {
            abrirModalAsignarCurso();
        }
    }

    const modalCambiar = document.getElementById('modalCambiarMaestro');
    if (modalCambiar) {
        modalCambiar.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalCambiarMaestro();
        });

        if (modalCambiar.dataset.open === '1' && modalCambiar.dataset.action) {
            modalCambiar.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modalAsignarAbierto = document.getElementById('modalAsignarCurso');
            if (modalAsignarAbierto && modalAsignarAbierto.style.display === 'flex') {
                cerrarModalAsignarCurso();
            }
            const modalCambiarAbierto = document.getElementById('modalCambiarMaestro');
            if (modalCambiarAbierto && modalCambiarAbierto.style.display === 'flex') {
                cerrarModalCambiarMaestro();
            }
        }
    });

    // Inicializar modal de asignar curso
    inicializarAsignarCursoModal();
});

export { formatearDui, inicializarFormatoDui, cargarMateriasCurso, inicializarAsignarCursoModal };

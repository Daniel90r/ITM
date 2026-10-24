<x-app-layout>
@push('styles')
<style>
    .boletas-alumnos-contenedor { container-type:inline-size; container-name:boletas-alumnos; }
    #gridAlumnos { display:grid; grid-template-columns:minmax(0,1fr); gap:16px; align-items:stretch; }
    #gridAlumnos .card-alumno { height:100%; min-width:0; box-sizing:border-box; }
    #gridAlumnos .boleta-alumno-contenido { display:flex; flex-direction:column; height:100%; min-width:0; }
    #gridAlumnos .boleta-alumno-info { flex:1; min-height:78px; }
    #gridAlumnos .boleta-alumno-avatar { width:44px !important; }
    #gridAlumnos .boleta-alumno-identidad { min-width:0; overflow-wrap:anywhere; }
    #gridAlumnos .boleta-alumno-identidad p { line-height:1.5; }
    #gridAlumnos .boleta-alumno-estadisticas { flex-shrink:0; }
    #gridAlumnos .boleta-alumno-enlace { flex-shrink:0; margin-top:auto; }
    @container boletas-alumnos (min-width:500px) { #gridAlumnos { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @container boletas-alumnos (min-width:760px) { #gridAlumnos { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @container boletas-alumnos (min-width:1020px) { #gridAlumnos { grid-template-columns:repeat(4,minmax(0,1fr)); } }
</style>
@endpush
<div style="display:flex; height:100vh; overflow:hidden;">
    @include('components.admin-sidebar', ['active' => 'boletas'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Boletas de Notas</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $grupos->count() }} cursos · {{ $grupos->sum(fn ($g) => $g['alumnos']->count()) }} alumnos</p>
                </div>
            </div>
            <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- NIVEL 1: CURSOS --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div id="vistaCursos" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">

            {{-- Buscador de cursos --}}
            <div style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; gap:12px; flex-shrink:0;">
                <div style="position:relative; flex:1; max-width:380px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="buscadorCurso" placeholder="Buscar curso..." autocomplete="off"
                        style="width:100%; padding:9px 14px 9px 34px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                        oninput="filtrarCursos()"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
                <p id="contadorCursos" style="font-size:12px; color:#9ca3af; margin:0; white-space:nowrap;">{{ $grupos->count() }} cursos</p>
            </div>

            {{-- Grid de cursos --}}
            <div style="flex:1; overflow-y:auto; padding:24px;">
                <div id="gridCursos" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:16px;">
                    @forelse($grupos as $grupo)
                    @php
                        $curso = $grupo['curso'];
                        $alumnosGrupo = $grupo['alumnos'];
                        $nombreCurso = $curso?->nombre ?? 'Sin curso';
                        $cursoId = $grupo['curso_id'];
                        $promedioCurso = $alumnosGrupo->map(fn($a) => $a->notas->avg('promedio'))->filter()->avg();
                        $conNotas = $alumnosGrupo->filter(fn($a) => $a->notas->count() > 0)->count();
                    @endphp
                    <div class="card-curso" data-curso-id="{{ $cursoId }}"
                        style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.04); transition:box-shadow .15s;"
                        onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.borderColor='#d1d5db'"
                        onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)'; this.style.borderColor='#e5e7eb'">

                        {{-- Info curso --}}
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                            <div style="width:44px; height:44px; border-radius:12px; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:15px; font-weight:700; flex-shrink:0;">
                                {{ strtoupper(substr($nombreCurso, 0, 1)) }}{{ strtoupper(substr(strrchr($nombreCurso, ' ') ?: ' ', 1, 1)) }}
                            </div>
                            <div>
                                <p style="font-size:14px; font-weight:700; color:#111827; margin:0;">{{ $nombreCurso }}</p>
                                <p style="font-size:11px; color:#9ca3af; margin:0;">{{ $alumnosGrupo->count() }} {{ $alumnosGrupo->count() === 1 ? 'alumno' : 'alumnos' }}</p>
                            </div>
                        </div>

                        {{-- Stats del curso --}}
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-top:1px solid #f3f4f6; border-bottom:1px solid #f3f4f6; margin-bottom:14px;">
                            <div style="text-align:center;">
                                <p style="font-size:11px; color:#6b7280; margin:0 0 2px;">Alumnos</p>
                                <p style="font-size:13px; font-weight:700; color:#111827; margin:0;">{{ $alumnosGrupo->count() }}</p>
                            </div>
                            <div style="text-align:center;">
                                <p style="font-size:11px; color:#6b7280; margin:0 0 2px;">Con notas</p>
                                <p style="font-size:13px; font-weight:700; color:#111827; margin:0;">{{ $conNotas }}</p>
                            </div>
                            <div style="text-align:center;">
                                <p style="font-size:11px; color:#6b7280; margin:0 0 2px;">Promedio</p>
                                <p style="font-size:18px; font-weight:700; margin:0;
                                    color:{{ $promedioCurso >= 8 ? '#16a34a' : ($promedioCurso >= 6 ? '#d97706' : ($promedioCurso > 0 ? '#dc2626' : '#9ca3af')) }};">
                                    {{ $promedioCurso ? round($promedioCurso, 1) : '—' }}
                                </p>
                            </div>
                        </div>

                        <button type="button" data-curso="{{ $cursoId }}" data-titulo="{{ $nombreCurso }}"
                            onclick="abrirCurso(this)"
                            style="display:block; width:100%; padding:9px; background:#111827; color:#fff; border:none; border-radius:8px; text-align:center; font-size:12px; font-weight:600; cursor:pointer; box-sizing:border-box;"
                            onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                            Ver alumnos →
                        </button>
                    </div>
                    @empty
                    <div style="grid-column:1/-1; text-align:center; padding:60px; color:#9ca3af; font-size:13px;">
                        No hay alumnos registrados.
                    </div>
                    @endforelse
                </div>

                <div id="sinResultadosCursos" style="display:none; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:40px; text-align:center; color:#9ca3af; font-size:13px; margin-top:4px;">
                    No se encontraron cursos para tu búsqueda.
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- NIVEL 2: ALUMNOS DEL CURSO SELECCIONADO --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div id="vistaAlumnos" style="flex:1; display:none; flex-direction:column; overflow:hidden;">

            {{-- Barra: volver + título + buscador de alumnos --}}
            <div style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; gap:14px; flex-shrink:0; flex-wrap:wrap;">
                <x-boton-regreso onclick="volverACursos()">Volver a Cursos</x-boton-regreso>

                <div style="display:flex; align-items:center; gap:8px;">
                    <h3 id="tituloCurso" style="font-size:15px; font-weight:700; color:#111827; margin:0;"></h3>
                    <span id="badgeAlumnosCurso" style="font-size:11px; color:#6b7280; background:#f3f4f6; padding:3px 10px; border-radius:6px; font-weight:600;"></span>
                </div>

                <div style="position:relative; flex:1 1 220px; max-width:320px; margin-left:auto;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="buscadorAlumno" placeholder="Buscar alumno en este curso..." autocomplete="off"
                        style="width:100%; padding:9px 14px 9px 34px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                        oninput="filtrarAlumnosCurso()"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                </div>

                <p id="contadorAlumnos" style="font-size:12px; color:#9ca3af; margin:0; white-space:nowrap;"></p>
            </div>

{{-- Grid de alumnos (todos los cursos; JS muestra solo el activo) --}}
            <div class="boletas-alumnos-contenedor" style="flex:1; overflow-y:auto; padding:24px;">
                <div id="gridAlumnos">
                    @foreach($grupos as $grupo)
                        @php
                            $cursoGrupo = $grupo['curso'];
                            $alumnosGrupo = $grupo['alumnos'];
                            $nombreCurso = $cursoGrupo?->nombre ?? 'Sin curso';
                            $cursoId = $grupo['curso_id'];
                        @endphp
                        @foreach($alumnosGrupo->sortBy([['apellido','asc'],['nombre','asc']]) as $alumno)
                        @php
                            $promedio = $alumno->notas->avg('promedio');
                        @endphp
                        <div class="card-alumno"
                            data-curso="{{ $cursoId }}"
                            data-nombre="{{ strtolower($alumno->nombre.' '.$alumno->apellido) }}"
                            data-codigo="{{ strtolower($alumno->codigo) }}"
                            style="display:none; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.04); transition:box-shadow .15s;"
                            onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.borderColor='#d1d5db'"
                            onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)'; this.style.borderColor='#e5e7eb'">

                            <div class="boleta-alumno-contenido">
                            {{-- Info alumno --}}
                            <div class="boleta-alumno-info" style="display:flex; align-items:flex-start; gap:12px; margin-bottom:16px;">
                                <div class="boleta-alumno-avatar" style="width:44px; height:44px; border-radius:50%; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px; font-weight:700; flex-shrink:0;">
                                    {{ strtoupper(substr($alumno->nombre,0,1).substr($alumno->apellido,0,1)) }}
                                </div>
                                <div class="boleta-alumno-identidad">
                                    <p style="font-size:13px; font-weight:600; color:#111827; margin:0;">{{ $alumno->nombre_completo }}</p>
                                    <p style="font-size:11px; color:#9ca3af; margin:0; font-family:monospace;">{{ $alumno->codigo }}</p>
                                </div>
                            </div>

                            {{-- Stats --}}
                            <div class="boleta-alumno-estadisticas" style="display:flex; justify-content:space-around; align-items:center; padding:10px 0; border-top:1px solid #f3f4f6; border-bottom:1px solid #f3f4f6; margin-bottom:14px;">
                                <div style="text-align:center;">
                                    <p style="font-size:11px; color:#6b7280; margin:0 0 2px;">Materias</p>
                                    <p style="font-size:13px; font-weight:700; color:#111827; margin:0;">{{ $alumno->notas->count() }}</p>
                                </div>
                                <div style="text-align:center;">
                                    <p style="font-size:11px; color:#6b7280; margin:0 0 2px;">Promedio</p>
                                    <p style="font-size:18px; font-weight:700; margin:0;
                                        color:{{ $promedio >= 8 ? '#16a34a' : ($promedio >= 6 ? '#d97706' : ($promedio > 0 ? '#dc2626' : '#9ca3af')) }};">
                                        {{ $promedio ? round($promedio, 1) : '—' }}
                                    </p>
                                </div>
                            </div>

                            <a class="boleta-alumno-enlace" href="{{ route('admin.boletas.preview', [$alumno, $cursoGrupo]) }}"
                               style="display:block; width:100%; padding:9px; background:#111827; color:#fff; border-radius:8px; text-align:center; font-size:12px; font-weight:600; text-decoration:none; box-sizing:border-box;"
                               onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                                 Ver boleta completa
                            </a>
                            </div>
                        </div>
                        @endforeach
                    @endforeach
                </div>

                <div id="sinResultadosAlumnos" style="display:none; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:40px; text-align:center; color:#9ca3af; font-size:13px; margin-top:4px;">
                    No se encontraron alumnos en este curso para tu búsqueda.
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // ═══════════════════════════════════════════════════════════
    // BOLETAS: navegación Cursos → Alumnos del curso
    // ═══════════════════════════════════════════════════════════
    let cursoActual = '';

    function abrirCurso(btn) {
        cursoActual = btn.dataset.curso;
        document.getElementById('vistaAlumnos').dataset.cursoActual = cursoActual;

        document.getElementById('vistaCursos').style.display  = 'none';
        document.getElementById('vistaAlumnos').style.display = 'flex';
        document.getElementById('tituloCurso').textContent    = btn.dataset.titulo;

        const buscador = document.getElementById('buscadorAlumno');
        buscador.value = '';
        filtrarAlumnosCurso();
        buscador.focus();
    }

    function volverACursos() {
        cursoActual = '';
        document.getElementById('vistaAlumnos').dataset.cursoActual = '';
        document.getElementById('vistaAlumnos').style.display = 'none';
        document.getElementById('vistaCursos').style.display  = 'flex';

        // Reiniciar búsqueda de cursos
        document.getElementById('buscadorCurso').value = '';
        filtrarCursos();
    }

    function filtrarCursos() {
        const q      = document.getElementById('buscadorCurso').value.toLowerCase().trim();
        const cards  = document.querySelectorAll('.card-curso');
        let visibles = 0;

        cards.forEach(card => {
            // Filtrar por ID del curso (para cursos con mismo nombre) y por nombre
            const cursoId = card.dataset.cursoId;
            const nombreCurso = card.querySelector('p')?.textContent?.toLowerCase() || '';
            const coincide = !q || cursoId.includes(q) || nombreCurso.includes(q);
            card.style.display = coincide ? 'block' : 'none';
            if (coincide) visibles++;
        });

        document.getElementById('contadorCursos').textContent = visibles + (visibles === 1 ? ' curso' : ' cursos');
        document.getElementById('sinResultadosCursos').style.display = (visibles === 0 && cards.length > 0) ? 'block' : 'none';
    }

    function filtrarAlumnosCurso() {
        const q      = document.getElementById('buscadorAlumno').value.toLowerCase().trim();
        const cards  = document.querySelectorAll('.card-alumno');
        let total    = 0;
        let visibles = 0;

        cards.forEach(card => {
            const esDelCurso = card.dataset.curso === cursoActual;
            if (esDelCurso) total++;

            const coincide = esDelCurso && (card.dataset.nombre.includes(q) || card.dataset.codigo.includes(q));
            card.style.display = coincide ? 'block' : 'none';
            if (coincide) visibles++;
        });

        document.getElementById('badgeAlumnosCurso').textContent = total + (total === 1 ? ' alumno' : ' alumnos');
        document.getElementById('contadorAlumnos').textContent   = q ? (visibles + ' de ' + total) : '';
        document.getElementById('sinResultadosAlumnos').style.display = (visibles === 0 && total > 0) ? 'block' : 'none';
    }
</script>

<x-logout-modal />
</x-app-layout>

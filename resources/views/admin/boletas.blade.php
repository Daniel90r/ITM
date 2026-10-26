<x-app-layout>
@push('styles')
<style>
    #boletas-panel .boletas-cursos-contenido{flex:1;min-height:0;overflow-y:auto;padding:20px 24px}
    #boletas-panel .boletas-filtros{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;margin-bottom:16px;min-width:0}
    #boletas-panel .boletas-filtros-titulo{font-size:14px;font-weight:700;color:#0f172a;margin:0}
    #boletas-panel .boletas-filtros-cabecera{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}
    #boletas-panel .boletas-campos{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}
    #boletas-panel .boletas-campo{min-width:0}
    #boletas-panel .boletas-campo label{display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:6px}
    #boletas-panel .boletas-campo :is(select,input){box-sizing:border-box;width:100%;min-width:0;min-height:42px;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;background:white;color:#0f172a;font-size:13px}
    #boletas-panel .boletas-filtros-pie{margin-top:12px;padding-top:12px;border-top:1px solid #f1f5f9}
    #boletas-panel .boletas-filtros-pie [data-consulta-cursos-alcance]>p{display:none}
    #boletas-panel .boletas-ayuda{font-size:12px;line-height:1.5;color:#64748b;margin:6px 0 0}
    #boletas-panel #gridCursos{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:16px;align-items:stretch}
    #boletas-panel .card-curso{display:flex;flex-direction:column;box-sizing:border-box;min-width:0;max-width:400px}
    #boletas-panel .boleta-curso-identidad{min-width:0;flex:1}
    #boletas-panel .boleta-curso-cabecera{flex-direction:row!important;flex-wrap:nowrap!important}
    #boletas-panel .boleta-curso-avatar{width:44px!important;height:44px;flex:0 0 44px!important}
    #boletas-panel .boleta-curso-identidad p{overflow-wrap:anywhere}
    #boletas-panel .boleta-curso-estado{display:inline-flex;align-items:center;gap:6px;border-radius:6px;padding:4px 8px;font-size:11px;font-weight:600;margin-top:8px;background:#f1f5f9;color:#475569}
    #boletas-panel .boleta-curso-estado[data-estado=ACTIVO]{background:#dcfce7;color:#166534}
    #boletas-panel .boleta-curso-estado::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0}
    #boletas-panel .boleta-curso-accion{margin-top:auto;min-height:42px}
    #boletas-panel :is(input,select,button,a):focus-visible{outline:3px solid #60a5fa;outline-offset:3px}
    @media(max-width:640px){#boletas-panel .boletas-cursos-contenido{padding:14px}#boletas-panel .boletas-filtros{padding:14px}#boletas-panel .boletas-campos,#boletas-panel #gridCursos{grid-template-columns:minmax(0,1fr)!important}}
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
<div id="boletas-panel" style="display:flex; height:100vh; overflow:hidden;">
    @include('components.admin-sidebar', ['active' => 'boletas'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Boletas de Notas</h2>
                    @php
                        $totalAlumnosBoletas = $grupos->sum(fn ($g) => $g['alumnos']->count());
                    @endphp
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $grupos->count() }} {{ $grupos->count() === 1 ? 'curso' : 'cursos' }} · {{ $totalAlumnosBoletas }} {{ $totalAlumnosBoletas === 1 ? 'alumno' : 'alumnos' }}</p>
                </div>
            </div>
            <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- NIVEL 1: CURSOS --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div id="vistaCursos" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">

            <div class="boletas-cursos-contenido">
                <section class="boletas-filtros" aria-labelledby="boletas-filtros-titulo">
                    <div class="boletas-filtros-cabecera">
                        <h3 id="boletas-filtros-titulo" class="boletas-filtros-titulo">Filtrar cursos</h3>
                        <p id="contadorCursos" role="status" style="font-size:12px;color:#64748b;margin:0">{{ $grupos->count() }} {{ $grupos->count() === 1 ? 'curso encontrado' : 'cursos encontrados' }}</p>
                    </div>
                    <form method="GET" action="{{ route('admin.boletas') }}" id="boletas-filtros-form">
                        <div class="boletas-campos">
                            <div class="boletas-campo">
                                <label for="boletas-curso">Seleccionar curso</label>
                                <select id="boletas-curso" name="curso_id" onchange="this.form.requestSubmit()" aria-describedby="boletas-filtros-ayuda"><x-consulta-cursos-opciones :cursos="$cursosFiltro" :filtros="$filtrosCursos" /></select>
                            </div>
                            <div class="boletas-campo">
                                <label for="buscadorCurso">Buscar curso</label>
                                <input type="text" id="buscadorCurso" placeholder="Nombre o identificador del curso" autocomplete="off" oninput="filtrarCursos()" onkeydown="if(event.key==='Enter'){event.preventDefault();filtrarCursos();}" aria-describedby="boletas-filtros-ayuda">
                            </div>
                        </div>
                        <div class="boletas-filtros-pie">
                            <x-consulta-cursos-alcance :filtros="$filtrosCursos" />
                            <p id="boletas-filtros-ayuda" class="boletas-ayuda">El selector consulta un curso; la búsqueda filtra las tarjetas visibles. Incluye históricos para consultar cursos finalizados.</p>
                        </div>
                        <noscript><button type="submit">Consultar cursos</button></noscript>
                    </form>
                </section>
                <div id="gridCursos">
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
                        <div class="boleta-curso-cabecera" style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                            <div class="boleta-curso-avatar" style="width:44px; height:44px; border-radius:12px; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:15px; font-weight:700; flex-shrink:0;">
                                {{ strtoupper(substr($nombreCurso, 0, 1)) }}{{ strtoupper(substr(strrchr($nombreCurso, ' ') ?: ' ', 1, 1)) }}
                            </div>
                            <div class="boleta-curso-identidad">
                                <p style="font-size:14px; font-weight:700; color:#111827; margin:0;">{{ $nombreCurso }}</p>
                                <p style="font-size:12px; color:#64748b; margin:4px 0 0;">{{ $curso?->nivel }} · Sección {{ $curso?->seccion }} · {{ $curso?->anio_lectivo }}</p>
                                <span class="boleta-curso-estado" data-estado="{{ $curso?->estado_curso }}">{{ $curso?->estado_curso === 'ACTIVO' ? 'Activo' : ($curso?->estado_curso === 'FINALIZADO' ? 'Finalizado' : 'Estado no disponible') }}</span>
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
                                <p style="font-size:11px; color:#6b7280; margin:0 0 2px;">Promedio general</p>
                                <p style="font-size:18px; font-weight:700; margin:0;
                                    color:{{ $promedioCurso >= 8 ? '#16a34a' : ($promedioCurso >= 6 ? '#d97706' : ($promedioCurso > 0 ? '#dc2626' : '#9ca3af')) }};">
                                    {{ $promedioCurso ? round($promedioCurso, 1) : '—' }}
                                </p>
                            </div>
                        </div>

                        <button class="boleta-curso-accion" type="button" data-curso="{{ $cursoId }}" data-titulo="{{ $nombreCurso }}"
                            onclick="abrirCurso(this)"
                            style="display:block; width:100%; padding:9px; background:#111827; color:#fff; border:none; border-radius:8px; text-align:center; font-size:12px; font-weight:600; cursor:pointer; box-sizing:border-box;"
                            onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                            Ver alumnos →
                        </button>
                    </div>
                    @empty
                    <div style="grid-column:1/-1; text-align:center; padding:60px; color:#9ca3af; font-size:13px;">
                        No hay cursos con alumnos inscritos para los filtros seleccionados.
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
            card.style.display = coincide ? 'flex' : 'none';
            if (coincide) visibles++;
        });

        document.getElementById('contadorCursos').textContent = visibles + (visibles === 1 ? ' curso encontrado' : ' cursos encontrados');
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

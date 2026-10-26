<x-app-layout>
<style>
#registro-notas .notas-content{padding:24px;display:grid;gap:20px;min-width:0}
#registro-notas .notas-card{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:20px;min-width:0}
#registro-notas .notas-filtros{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:14px}
#registro-notas label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
#registro-notas input,#registro-notas select{width:100%;min-height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;box-sizing:border-box}
#registro-notas .notas-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;margin-top:16px}
#registro-notas .notas-button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:8px 16px;border-radius:8px;background:#0f172a;color:white;text-decoration:none;border:1px solid #0f172a;font-weight:600;font-size:13px}
#registro-notas .notas-secondary{background:white;color:#0f172a;border-color:#cbd5e1}
#registro-notas :focus-visible{outline:3px solid #60a5fa;outline-offset:3px}
#registro-notas summary{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:16px;list-style:none;flex-wrap:wrap}
#registro-notas summary::-webkit-details-marker{display:none}
#registro-notas .notas-hide{display:none}
#registro-notas details[open] .notas-show{display:none}
#registro-notas details[open] .notas-hide{display:inline}
#registro-notas .notas-table{overflow-x:auto;margin-top:12px}
#registro-notas table{width:100%;border-collapse:collapse;min-width:580px;font-size:13px}
#registro-notas th,#registro-notas td{padding:12px;text-align:center;border-bottom:1px solid #e2e8f0}
#registro-notas th{background:#f8fafc;color:#475569}
#registro-notas th:first-child,#registro-notas td:first-child{text-align:left}
#registro-notas .notas-average{display:inline-flex;align-items:center;justify-content:center;min-width:32px;min-height:32px;border-radius:50%;color:white;font-weight:700}
@media(max-width:600px){#registro-notas .notas-content{padding:14px}#registro-notas .notas-card{padding:14px}}
</style>
<div id="registro-notas" style="display:flex;height:100vh;overflow:hidden">
@include('components.admin-sidebar', ['active' => 'notas'])
<main style="flex:1;min-width:0;overflow-y:auto;background:#f8fafc;color:#0f172a">
<header style="background:white;border-bottom:1px solid #e2e8f0;padding:20px 24px">
<h2 style="font-size:20px;font-weight:700;margin:0">Registro de Notas</h2>
<p style="color:#64748b;margin:4px 0 0">Consulta las materias y calificaciones registradas de cada alumno.</p>
</header>
<div class="notas-content">
<form method="GET" action="{{ route('admin.notas') }}" class="notas-card"><x-consulta-cursos-alcance :filtros="$filtros" />
<div class="notas-filtros">
<div><label for="notas-alumno">Alumno o código</label><input id="notas-alumno" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Nombre, apellido o código"></div>
<div><label for="notas-curso">Curso</label><select id="notas-curso" name="curso_id" onchange="this.form.elements.materia_id.value='';this.form.requestSubmit()"><x-consulta-cursos-opciones :cursos="$cursos" :filtros="$filtros" /></select></div>
<div><label for="notas-materia">Materia</label><select id="notas-materia" name="materia_id"><option value="">Todas las materias</option>@foreach($materias as $materia)<option value="{{ $materia->id }}" @selected(($filtros['materia_id'] ?? '') == $materia->id)>{{ $materia->nombre }}</option>@endforeach</select></div>
<div><label for="notas-docente">Docente asignado</label><select id="notas-docente" name="maestro_id"><option value="">Todos los docentes</option>@foreach($maestros as $maestro)<option value="{{ $maestro->id }}" @selected(($filtros['maestro_id'] ?? '') == $maestro->id)>{{ $maestro->nombre }} {{ $maestro->apellido }}</option>@endforeach</select></div>
</div>
@foreach($errors->all() as $error)<p role="alert" style="color:#991b1b">{{ $error }}</p>@endforeach
<div class="notas-actions"><a href="{{ route('admin.notas') }}" class="notas-button notas-secondary">Limpiar filtros</a><button class="notas-button" type="submit">Filtrar</button></div>
</form>
<p style="margin:0;color:#64748b" role="status">{{ $alumnos->total() }} {{ $alumnos->total() === 1 ? 'alumno con notas registradas' : 'alumnos con notas registradas' }}</p>
@forelse($alumnos as $alumno)
<details class="notas-card" data-notas-alumno="{{ $alumno->id }}">
<summary><div><strong style="display:block;overflow-wrap:anywhere">{{ $alumno->nombre_completo }}</strong><span style="font-size:13px;color:#64748b">{{ $alumno->codigo }} · {{ $alumno->notas->pluck('detalle_curso_id')->unique()->count() }} materias con notas</span></div><span class="notas-button notas-secondary"><span class="notas-show">Ver notas</span><span class="notas-hide">Ocultar notas</span></span></summary>
@foreach($alumno->notas->groupBy(fn($nota) => $nota->detalleCurso->curso_id) as $notasCurso)
@php($cursoNota = $notasCurso->first()->detalleCurso->curso)
<section style="margin-top:20px">
<h3 style="font-size:14px;font-weight:700">{{ $cursoNota?->nombre ?? 'Curso no disponible' }} · {{ $cursoNota?->nivel }} · {{ $cursoNota?->seccion }} · {{ $cursoNota?->anio_lectivo }}</h3>
<div class="notas-table" tabindex="0" role="region" aria-label="Calificaciones de {{ $cursoNota?->nombre ?? 'curso no disponible' }}">
<table><thead><tr><th scope="col">Materia</th><th scope="col">Laboratorio</th><th scope="col">Examen teórico</th><th scope="col">Práctico</th><th scope="col">Promedio</th><th scope="col">Conducta</th></tr></thead><tbody>
@foreach($notasCurso as $nota)
<tr data-nota="{{ $nota->id }}"><td>{{ $nota->detalleCurso->materia->nombre ?? 'Materia no disponible' }}</td><td>{{ $nota->laboratorio ?? '—' }}</td><td>{{ $nota->examen_teorico ?? '—' }}</td><td>{{ $nota->practica ?? '—' }}</td><td><span class="notas-average" style="background:{{ $nota->promedio === null ? '#64748b' : ($nota->promedio >= 8 ? '#16a34a' : ($nota->promedio >= 6 ? '#d97706' : '#dc2626')) }}">{{ $nota->promedio ?? '—' }}</span></td><td>{{ $nota->conducta ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></section>
@endforeach
</details>
@empty
<div class="notas-card" style="text-align:center;color:#64748b">No se encontraron alumnos con notas para los filtros seleccionados.</div>
@endforelse
{{ $alumnos->links() }}
</div></main></div>
<x-logout-modal />
</x-app-layout>

<x-admin-reportes-layout>
    <x-slot:encabezado>
        <x-boton-regreso :href="route('admin.reportes')">Volver a Reportes</x-boton-regreso>
        <h1 class="text-2xl font-bold mt-2">Reporte de {{ $tipos[$tipo] }}</h1>
    </x-slot:encabezado>
    @if($errors->any())<div role="alert" class="p-4 rounded-lg bg-red-50 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="GET" action="{{ route('admin.reportes.show','cursos') }}" class="reportes-filters mt-6" id="filtros-cursos">
        <label>Curso<select name="curso_id"><option value="">Todos los cursos</option>@foreach($cursos as $curso)<option value="{{ $curso->id }}" @selected(($filtros['curso_id'] ?? '') == $curso->id)>{{ $curso->nombre }} · {{ $curso->nivel }} · {{ $curso->seccion }} · {{ $curso->anio_lectivo }}</option>@endforeach</select></label>
        <label>Estado actual del curso<select name="estado"><option value="">Todos los estados</option>@foreach($estados as $valor=>$etiqueta)<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach</select></label>
        <label>Año<input type="number" name="anio" value="{{ $filtros['anio'] }}" min="1900" max="2100" required></label>
        <div class="reportes-actions"><button type="submit">Aplicar filtros</button><a href="{{ route('admin.reportes.show','cursos') }}">Limpiar</a><x-reportes-descargar-pdf id="descarga-cursos" :href="route('admin.reportes.cursos.pdf',$filtros)" :preview-href="route('admin.reportes.visor', ['tipo'=>'cursos'] + $filtros)" /></div>
    </form>
    <p class="text-sm text-gray-500 mt-3">Año: {{ $filtros['anio'] }}. Cursos con inscripciones actualmente aprobadas cuya fecha registrada de solicitud/inscripción corresponde al año. No indica cuándo fueron aprobadas.</p>
    <p class="text-sm text-gray-500 mt-2">{{ $reporte['descripcion'] ?? 'Estado, visibilidad, cupos y disponibilidad son actuales, no históricos.' }}</p>
    <p id="cursos-pendientes" class="text-sm text-gray-500" hidden>Aplica los filtros modificados antes de descargar.</p>
    <dl class="reportes-summary">@foreach($reporte['resumen'] as $etiqueta=>$valor)<div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>@endforeach</dl>
    <div class="reportes-table" role="region" aria-label="Cursos del año" tabindex="0"><table><thead><tr>@foreach($reporte['columnas'] as $columna)<th scope="col">{{ $columna }}</th>@endforeach</tr></thead><tbody>@forelse($reporte['filas'] as $fila)<tr>@foreach($fila as $valor)<td>{{ $valor }}</td>@endforeach</tr>@empty<tr><td colspan="7">No hay cursos con inscripciones actualmente aprobadas cuya fecha registrada corresponda al año seleccionado.</td></tr>@endforelse</tbody></table></div>
    {{ $reporte['filas']->links() }}
    <script>
    (()=>{
        const form=document.getElementById('filtros-cursos'), pdf=document.getElementById('descarga-cursos');
        const invalidar=()=>{pdf.removeAttribute('href');pdf.setAttribute('aria-disabled','true');form.querySelectorAll('.reportes-preview').forEach(a=>{a.removeAttribute('href');a.setAttribute('aria-disabled','true');});document.getElementById('cursos-pendientes').hidden=false;};
        form.addEventListener('input',invalidar);form.addEventListener('change',invalidar);
    })();
    </script>
</x-admin-reportes-layout>

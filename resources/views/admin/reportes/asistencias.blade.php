<x-admin-reportes-layout>
    <x-slot:encabezado>
        <x-boton-regreso :href="route('admin.reportes')">Volver a Reportes</x-boton-regreso>
        <h1 class="text-2xl font-bold mt-2">Reporte de {{ $tipos[$tipo] }}</h1>
        <p class="text-sm text-gray-500 mt-2">Consulta el historial por curso, materia y período.</p>
    </x-slot:encabezado>
    @push('styles')
    <style>
        .asistencia-reporte{margin-top:24px}.asistencia-reporte .reportes-filters{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px;padding:20px}
        .asistencia-reporte .campo-amplio{grid-column:span 3}.asistencia-reporte .campo-fecha{grid-column:span 2}
        .asistencia-reporte select,.asistencia-reporte input{width:100%;background:#fff;padding:9px;color:#334155}
        .asistencia-reporte .reportes-filters [hidden]{display:none!important}
        .asistencia-reporte .ayuda{font-size:12px;color:#64748b;margin:12px 0;line-height:1.6}
        .asistencia-reporte .aviso{padding:12px 16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:13px;margin-bottom:16px}
        .asistencia-reporte .reportes-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
        .asistencia-reporte .reportes-summary div{min-width:0;border-top:3px solid #94a3b8}.asistencia-reporte .reportes-summary div:nth-child(2){border-top-color:#059669}.asistencia-reporte .reportes-summary div:nth-child(3){border-top-color:#e11d48}.asistencia-reporte .reportes-summary div:nth-child(4){border-top-color:#d97706}
        .asistencia-reporte .reportes-summary dd{font-size:24px}.asistencia-reporte .reportes-table td{padding:12px 16px}.asistencia-reporte .reportes-table th{padding:12px 16px}
        @media(max-width:1023px){.asistencia-reporte .reportes-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.asistencia-reporte .campo-amplio,.asistencia-reporte .campo-fecha{grid-column:span 1}}
        @media(max-width:640px){.asistencia-reporte .reportes-filters{grid-template-columns:1fr;padding:16px}.asistencia-reporte .reportes-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.asistencia-reporte .reportes-table table{min-width:660px}}
    </style>
    @endpush
    <section class="asistencia-reporte" aria-label="Consulta de asistencias">
        @if($errors->any())
            <div class="aviso" role="alert"><strong>Revisa los filtros:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form id="filtros-asistencia" method="GET" action="{{ route('admin.reportes.show', 'asistencias') }}" class="reportes-filters">
            <label class="campo-amplio">Curso
                <select name="curso_id" id="curso-asistencia" aria-describedby="ayuda-materias">
                    <option value="">Todos los cursos</option>
                    @foreach($cursos as $curso)
                        <option value="{{ $curso->id }}" @selected(($filtros['curso_id'] ?? '') == $curso->id)>{{ $curso->nombre }} · {{ $curso->nivel }} · {{ $curso->seccion }} · {{ $curso->anio_lectivo }}</option>
                    @endforeach
                </select>
            </label>
            <label class="campo-amplio">Materia del curso
                <select name="detalle_curso_id" id="materia-asistencia" @disabled($detalleCursos->isEmpty()) aria-describedby="ayuda-materias">
                    <option value="">{{ empty($filtros['curso_id']) ? 'Selecciona un curso' : ($detalleCursos->isEmpty() ? 'Sin materias asignadas' : 'Todas las materias') }}</option>
                    @foreach($detalleCursos as $detalle)
                        <option value="{{ $detalle->id }}" @selected(($filtros['detalle_curso_id'] ?? '') == $detalle->id)>{{ $detalle->materia?->nombre ?? 'Materia no disponible' }} · Asignación {{ $detalle->id }}</option>
                    @endforeach
                </select>
            </label>
            <label class="campo-fecha">Modalidad
                <select name="modalidad" id="modalidad-asistencia">
                    <option value="mensual" @selected(($filtros['modalidad'] ?? '') === 'mensual')>Mensual</option>
                    <option value="anual" @selected(($filtros['modalidad'] ?? '') === 'anual')>Anual</option>
                </select>
            </label>
            <label class="campo-fecha" id="campo-mes-asistencia" @if(($filtros['modalidad'] ?? '') !== 'mensual') hidden @endif>Mes
                <select name="mes" id="mes-asistencia" @disabled(($filtros['modalidad'] ?? '') !== 'mensual')>
                    @foreach(\App\Services\ReportesAdministrativos::MESES as $numero => $mes)<option value="{{ $numero }}" @selected(($filtros['mes'] ?? now()->month) == $numero)>{{ $mes }}</option>@endforeach
                </select>
            </label>
            <label class="campo-fecha" id="campo-anio-asistencia">Año<input id="anio-asistencia" type="number" name="anio" min="1900" max="2100" value="{{ $filtros['anio'] ?? now()->year }}" required></label>
            <label class="campo-fecha">Estado
                <select name="estado"><option value="">Todos los estados</option>@foreach($estados as $valor => $etiqueta)<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach</select>
            </label>
            <div class="reportes-actions">
                <button type="submit">Aplicar filtros</button>
                <a class="text-sm underline" href="{{ route('admin.reportes.show', 'asistencias') }}">Limpiar</a>
                @if(!empty($filtros['curso_id']) && !empty($filtros['detalle_curso_id']))
                    <x-reportes-descargar-pdf id="descarga-asistencia" :href="route('admin.asistencias.pdf', $filtros)" :preview-href="route('admin.reportes.visor', ['tipo' => 'asistencias'] + $filtros)" />
                @else
                    <x-reportes-descargar-pdf :disabled="true" aria-describedby="ayuda-pdf" />
                @endif
            </div>
        </form>
        <p id="ayuda-materias" class="ayuda">Las materias se actualizan automáticamente al cambiar de curso. <noscript>Sin JavaScript, pulsa Aplicar filtros para cargarlas.</noscript></p>
        @if(!empty($filtros['curso_id']) && $detalleCursos->isEmpty())
            <p class="aviso" role="status">Este curso no tiene materias asignadas. Puedes consultar su historial, pero no descargar un control por materia.</p>
        @endif
        <div id="ayuda-pdf" class="ayuda">
            <p>PDF histórico {{ $filtros['modalidad'] }}: incluye registros reales del período y estado seleccionados, aunque el alumno ya no esté inscrito. Para consultar las fechas del resumen anual, selecciona Mensual.</p>
            <p id="filtros-pendientes" hidden role="status">Hay cambios pendientes. Aplica los filtros antes de descargar.</p>
            <details><summary class="cursor-pointer underline">¿Qué incluye el PDF?</summary><p>Mensual muestra fechas y marcas individuales. Anual resume P/A/PM por mes. Sin registro no equivale a ausencia; los duplicados se señalan y no suman a los totales.</p></details>
        </div>
        <dl class="reportes-summary">@foreach($reporte['resumen'] as $etiqueta => $valor)<div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>@endforeach</dl>
        <div class="reportes-table" role="region" aria-label="Resultados de Asistencias" tabindex="0">
            <table><caption class="sr-only">Asistencias: resultados de los filtros seleccionados</caption>
                <thead><tr>@foreach($reporte['columnas'] as $columna)<th scope="col">{{ $columna }}</th>@endforeach</tr></thead>
                <tbody>@forelse($reporte['filas'] as $fila)<tr>@foreach($fila as $valor)<td>{{ $valor === null || $valor === '' ? '—' : $valor }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($reporte['columnas']) }}">No hay registros para los filtros seleccionados.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <p class="ayuda">{{ $reporte['filas']->total() }} resultados · 25 por página. Los indicadores abarcan todos los resultados filtrados. Sin registro no significa ausente.</p>
        {{ $reporte['filas']->links() }}
    </section>
    <script>
        (() => {
            const form = document.getElementById('filtros-asistencia');
            const curso = document.getElementById('curso-asistencia');
            const materia = document.getElementById('materia-asistencia');
            const descarga = document.getElementById('descarga-asistencia');
            const invalidarDescarga = () => {
                document.getElementById('filtros-pendientes').hidden = false;
                if (descarga) { descarga.removeAttribute('href'); descarga.setAttribute('aria-disabled', 'true'); }
                form.querySelectorAll('.reportes-preview').forEach(a => { a.removeAttribute('href'); a.setAttribute('aria-disabled', 'true'); });
            };
            const modalidad = document.getElementById('modalidad-asistencia');
            const actualizarPeriodo = () => {
                const valor = modalidad.value;
                for (const [campo, activo] of [['mes', valor === 'mensual'], ['anio', true]]) {
                    document.getElementById('campo-'+campo+'-asistencia').hidden = !activo;
                    const input = document.getElementById(campo+'-asistencia');
                    input.disabled = !activo;
                    input.required = activo && (campo === 'mes' || campo === 'anio');
                }
            };
            if (modalidad) { modalidad.addEventListener('change', actualizarPeriodo); actualizarPeriodo(); }
            form.addEventListener('input', invalidarDescarga);
            form.addEventListener('change', invalidarDescarga);
            curso.addEventListener('change', () => {
                materia.value = '';
                materia.disabled = true;
                form.setAttribute('aria-busy', 'true');
                form.requestSubmit();
            });
        })();
    </script>
</x-admin-reportes-layout>

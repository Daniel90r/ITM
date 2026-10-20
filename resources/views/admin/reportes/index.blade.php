<x-app-layout>
    @push('styles')
    <style>
        /* El body compartido es azul: Reportes ocupa el viewport, sin footer propio. */
        .reportes-layout{position:fixed;inset:0;display:flex;height:100vh;height:100dvh;overflow:hidden;background:#f8fafc;color:#111827}
        .reportes-sidebar{flex:0 0 220px;height:100vh;height:100dvh;min-height:0;overflow:hidden}
        /* details puede dejar indefinida la altura porcentual de su contenido. */
        .reportes-sidebar aside{height:100vh;height:100dvh;min-height:0;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain}
        .reportes-sidebar aside > *{flex-shrink:0}
        .reportes-main{flex:1;min-width:0;min-height:0;overflow-y:auto;overflow-x:hidden;padding:24px;max-width:1600px}
        .reportes-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:20px 0}
        .reportes-card{display:block;border:1px solid #e5e7eb;border-radius:12px;background:white;padding:16px;color:#374151;text-decoration:none}
        .reportes-card:hover,.reportes-card[aria-current=page]{border-color:#111827;background:#f1f5f9}
        .reportes-filters{display:flex;flex-wrap:wrap;align-items:end;gap:12px;padding:20px;background:white;border:1px solid #e5e7eb;border-radius:12px}
        .reportes-filters label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:600;max-width:100%}
        .reportes-filters select,.reportes-filters input{border:1px solid #d1d5db;border-radius:8px;font-size:13px;max-width:100%;min-height:40px}
        .reportes-filters button{background:#111827;color:white;padding:10px 18px;border-radius:8px;font-size:13px}
        .reportes-summary{display:flex;flex-wrap:wrap;gap:12px;margin:20px 0}
        .reportes-summary div{background:white;border:1px solid #e5e7eb;border-radius:12px;padding:16px;min-width:150px}
        .reportes-summary dt{color:#6b7280;font-size:12px}.reportes-summary dd{font-size:24px;font-weight:700;margin-top:4px}
        .reportes-table{max-width:100%;overflow:auto;scrollbar-color:#94a3b8 #f1f5f9;background:white;border:1px solid #e5e7eb;border-radius:12px;margin:16px 0}
        .reportes-table::-webkit-scrollbar-track{background:#f1f5f9}
        .reportes-table::-webkit-scrollbar-thumb{background:#94a3b8}
        .reportes-table table{width:100%;border-collapse:collapse;font-size:13px;text-align:left}
        .reportes-table th{background:#f1f5f9;font-size:12px;white-space:nowrap}
        .reportes-table th,.reportes-table td{padding:14px 16px;border-bottom:1px solid #e5e7eb;vertical-align:top}
        .reportes-table tbody tr:hover{background:#f8fafc}
        .reportes-sidebar summary{display:none}
        @media(max-width:900px){.reportes-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){.reportes-layout{position:static;inset:auto;flex-direction:column;height:auto;min-height:100vh;min-height:100dvh;overflow:visible}.reportes-sidebar{flex:0 0 auto;height:auto;overflow:visible}.reportes-main{padding:16px;overflow:visible}.reportes-sidebar summary{display:block;padding:12px 16px;background:white;cursor:pointer;border-bottom:1px solid #e5e7eb}.reportes-sidebar aside{width:100%!important;height:auto;max-height:55vh}.reportes-cards{grid-template-columns:1fr}.reportes-filters label{width:100%}.reportes-summary div{flex:1}}
    </style>
    @endpush
    <div class="reportes-layout">
        <details class="reportes-sidebar" open>
            <summary>Menú de administración</summary>
            @include('components.admin-sidebar', ['active' => 'reportes'])
        </details>
        <main class="reportes-main">
            <header>
                <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-500 underline">Panel principal</a>
                <h1 class="text-2xl font-bold mt-2">Reportes</h1>
                <p class="text-sm text-gray-500 mt-1">Consulta académica y administrativa · Solo lectura</p>
            </header>
            <nav class="reportes-cards" aria-label="Tipos de reporte">
                @foreach($tipos as $clave => $titulo)
                    <a class="reportes-card" href="{{ route('admin.reportes.show', $clave) }}" @if($tipo === $clave) aria-current="page" @endif>
                        <span class="text-xs text-gray-500">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="block font-semibold mt-1">{{ $titulo }} <span aria-hidden="true">↗</span></span>
                    </a>
                @endforeach
            </nav>
            @if($tipo)
                <h2 class="text-xl font-semibold mb-3">{{ $tipos[$tipo] }}</h2>
                @if($errors->any())
                    <div role="alert" class="mb-4 p-4 rounded-lg bg-red-50 text-red-700">
                        <p>Revisa los filtros:</p>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="GET" action="{{ route('admin.reportes.show', $tipo) }}" class="reportes-filters">
                    <label>Curso
                        <select name="curso_id">
                            <option value="">Todos los cursos</option>
                            @foreach($cursos as $curso)
                                <option value="{{ $curso->id }}" @selected(($filtros['curso_id'] ?? '') == $curso->id)>{{ $curso->nombre }} · {{ $curso->nivel }} · {{ $curso->seccion }} · {{ $curso->anio_lectivo }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($estados)
                        <label>{{ $tipo === 'alumnos' ? 'Estado del alumno' : 'Estado' }}
                            <select name="estado"><option value="">Todos los estados</option>
                                @foreach($estados as $valor => $etiqueta)<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach
                            </select>
                        </label>
                    @endif
                    @if($tipo === 'calificaciones')
                        <label>Materia<select name="materia_id"><option value="">Todas las materias activas</option>
                            @foreach($materias as $materia)<option value="{{ $materia->id }}" @selected(($filtros['materia_id'] ?? '') == $materia->id)>{{ $materia->nombre }}</option>@endforeach
                        </select></label>
                    @endif
                    @if($tipo === 'asistencias')
                        <label>Desde<input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}"></label>
                        <label>Hasta<input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}"></label>
                    @endif
                    @if($tipo === 'mensualidades')
                        <label>Año<input type="number" name="anio" min="1900" max="2100" placeholder="Todos" value="{{ $filtros['anio'] ?? '' }}"></label>
                        <label>Mes<select name="mes"><option value="">Todos los meses</option>
                            @foreach(\App\Services\ReportesAdministrativos::MESES as $numero => $mes)
                                <option value="{{ $numero }}" @selected(($filtros['mes'] ?? '') == $numero)>{{ $mes }}</option>
                            @endforeach
                        </select></label>
                    @endif
                    <button type="submit">Aplicar filtros</button>
                    <a href="{{ route('admin.reportes.show', $tipo) }}" class="text-sm underline p-2">Limpiar</a>
                </form>
                <dl class="reportes-summary">
                    @foreach($reporte['resumen'] as $etiqueta => $valor)<div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>@endforeach
                </dl>
                <p class="text-sm text-gray-600">{{ $reporte['descripcion'] }}</p>
                <div class="reportes-table" role="region" aria-label="Resultados de {{ $tipos[$tipo] }}" tabindex="0">
                    <table>
                        <caption class="sr-only">{{ $tipos[$tipo] }}: resultados de los filtros seleccionados</caption>
                        <thead><tr>@foreach($reporte['columnas'] as $columna)<th scope="col">{{ $columna }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse($reporte['filas'] as $fila)
                                <tr>@foreach($fila as $valor)<td>{{ $valor === null || $valor === '' ? '—' : $valor }}</td>@endforeach</tr>
                            @empty
                                <tr><td colspan="{{ count($reporte['columnas']) }}" class="text-center text-gray-500">No hay registros para los filtros seleccionados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 mb-3">{{ $reporte['filas']->total() }} resultados · 25 por página. Los indicadores abarcan todos los resultados filtrados.</p>
                {{ $reporte['filas']->links() }}
            @else
                <section class="reportes-card"><h2 class="font-semibold">Selecciona un reporte para comenzar</h2><p class="text-sm text-gray-500 mt-2">Usa las tarjetas para consultar registros y aplicar filtros. Cada reporte explica qué datos incluye y cómo se calculan sus indicadores.</p></section>
            @endif
        </main>
    </div>
</x-app-layout>

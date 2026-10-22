<x-app-layout>
<div class="page-layout admin-sidebar">
    @include('components.admin-sidebar', ['active' => 'dashboard'])
    {{-- Esta es la parte de estadisticas que muestra datos como gagos, promedios y cosas asi --}}
    <div class="main-content main-content-alt">

        <header class="page-header">
            <div>
                <h2 style="font-size:17px; font-weight:700; margin:0; color:#0f172a;">Panel del Director</h2>
                <p style="font-size:12px; color:#94a3b8; margin:0;">Ciclo escolar {{ now()->year }} · I.T.M. Aguilares</p>
            </div>
            <span style="font-size:12px; color:#94a3b8;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div class="content-body">

            <div class="banner-dark">
                <div>
                    <h3 style="font-size:16px; font-weight:700; color:#ffffff; margin:0 0 4px;">Bienvenido, {{ Auth::user()->name }}</h3>
                    <p style="font-size:13px; color:#94a3b8; margin:0;">Resumen del ciclo escolar {{ now()->year }}</p>
                </div>
                <div style="display:flex; gap:12px;">
                    <div class="banner-stat">
                        <p style="font-size:22px; font-weight:700; color:#4ade80; margin:0;">{{ $pagosAlDia }}%</p>
                        <p style="font-size:11px; color:#86efac; margin:0;">Pagos al día</p>
                    </div>
                    <div class="banner-stat">
                        <p style="font-size:22px; font-weight:700; color:#4ade80; margin:0;">{{ $promedioGeneral !== null ? number_format($promedioGeneral, 1) : '—' }}</p>
                        <p style="font-size:11px; color:#86efac; margin:0;">Promedio general</p>
                    </div>
                </div>
            </div>
            {{-- Aqui es para mostrar los alumnos, maestros, materias y pagos pendientes y que tambien
             al tocar esas partaes nosllevara a la parte que correponde  --}}
            <div class="card-grid-4">
                @foreach([
                    [$totalAlumnos,'Alumnos inscritos','#dbeafe','#1d4ed8','alumnos', route('admin.alumnos')],
                    [$totalMaestros,'Maestros activos','#fef3c7','#b45309','maestros', route('admin.maestros')],
                    [$totalMaterias,'Materias activas','#d1fae5','#065f46','materias', route('admin.materias')],
                    [$pagosPendientes,'Pagos pendientes','#fee2e2','#991b1b','pagos', route('admin.pagos')],
                ] as [$val,$label,$bg,$color,$icono,$href])
                <a href="{{ $href }}" style="text-decoration:none;">
                    <div class="stat-card-white">
                        <div class="stat-icon-box" style="background:{{ $bg }};">
                            <x-icono :nombre="$icono" tamano="18" :stroke="$color" />
                        </div>
                        <p style="font-size:26px; font-weight:700; color:#0f172a; margin:0 0 4px;">{{ $val }}</p>
                        <p style="font-size:12px; color:#64748b; margin:0;">{{ $label }}</p>
                    </div>
                </a>
                @endforeach
            </div>
            {{-- Y aqui va amostrar los maestros que estan ativos  --}}
            <div class="card-grid-2">

                <div class="card">
                    <div class="card-header">
                        <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">Maestros activos</p>
                        <a href="{{ route('admin.maestros') }}" class="btn-link" style="color:#16a34a;">Gestionar</a>
                    </div>
                    @php $coloresAvatar = ['#1d4ed8', '#7c3aed', '#0f766e', '#b45309', '#be123c']; @endphp
                    @forelse($maestrosActivos as $maestro)
                    @php
                        $materiasMaestro = $maestro->detalleCursos->pluck('materia.nombre')->filter()->unique()->implode(', ');
                    @endphp
                    <div style="display:flex; align-items:center; gap:12px; padding:12px 18px; {{ !$loop->first ? 'border-top:1px solid #f1f5f9;' : '' }}">
                        <div class="icon-box-sm" style="background:{{ $coloresAvatar[$loop->index % count($coloresAvatar)] }}; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; font-weight:700; flex-shrink:0;">
                            {{ strtoupper(substr($maestro->nombre, 0, 1) . substr($maestro->apellido, 0, 1)) }}
                        </div>
                        <div style="flex:1; overflow:hidden;">
                            <p style="font-size:13px; font-weight:500; color:#0f172a; margin:0;">{{ $maestro->nombre }} {{ $maestro->apellido }}</p>
                            <p style="font-size:11px; color:#94a3b8; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $materiasMaestro ?: 'Sin materias asignadas' }}</p>
                        </div>
                        <span class="stat-badge-green">Activo</span>
                    </div>
                    @empty
                    <div style="padding:20px 18px; text-align:center; color:#94a3b8; font-size:12px;">
                        No hay maestros registrados.
                    </div>
                    @endforelse
                </div>
                {{-- Aqui se muestra datos de el estado de mensaualidades  --}}
                <div class="card">
                    <div class="card-header">
                        <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">Estado de mensualidades</p>
                        <a href="{{ route('admin.mensualidades') }}" class="btn-link" style="color:#16a34a;">Ver</a>
                    </div>
                    @forelse($estadoMensualidades as $m)
                    <div style="display:flex; align-items:center; gap:10px; padding:10px 18px; border-top:1px solid #f8fafc;">
                        <p style="font-size:13px; color:#334155; margin:0; flex:1;">{{ $m['mes'] }} {{ $m['anio'] }}</p>
                        <span style="font-size:11px; color:#94a3b8;">{{ $m['pagadas'] }}/{{ $m['total'] }} pagadas</span>
                        <span style="font-size:11px; background:{{ $m['completo'] ? '#d1fae5' : '#fef3c7' }}; color:{{ $m['completo'] ? '#065f46' : '#92400e' }}; padding:3px 10px; border-radius:20px; font-weight:600;">
                            {{ $m['completo'] ? 'Pagado' : 'Pendiente' }}
                        </span>
                    </div>
                    @empty
                    <div style="padding:20px 18px; text-align:center; color:#94a3b8; font-size:12px;">
                        Aún no se han generado mensualidades.
                    </div>
                    @endforelse
                </div>

            </div>
        </div>
    </div>
</div>
</x-app-layout>

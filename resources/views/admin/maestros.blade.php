<x-app-layout>
@push('styles')
<style>{!! file_get_contents(resource_path('css/admin/maestros-listado.css')) !!}</style>
@endpush
<div class="gestion-maestros" style="display:flex;height:100vh;overflow:hidden;">
    @include('components.admin-sidebar', ['active' => 'maestros'])
    <div class="gm-panel">
        <header class="gm-header">
            <div class="gm-brand"><img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM"><div><h1>Gestión de Maestros</h1><p>{{ $maestros->count() }} {{ $maestros->count() === 1 ? 'maestro registrado' : 'maestros registrados' }}</p></div></div>
            <div class="gm-header-actions"><span class="gm-month">{{ now()->isoFormat('MMMM YYYY') }}</span><button type="button" class="gm-button gm-primary" onclick="abrirModalMaestro()">+ Nuevo Maestro</button></div>
        </header>
        <div class="gm-search-bar">
            <div class="gm-search"><label class="gm-sr-only" for="buscadorMaestro">Buscar maestro por nombre</label><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg><input type="search" id="buscadorMaestro" placeholder="Buscar maestro..." oninput="filtrarMaestros()"></div>
            <p id="contadorMaestros" role="status" aria-live="polite"></p>
            <button type="button" class="gm-button gm-secondary" onclick="limpiarBusquedaMaestros()">Limpiar</button>
        </div>
        <div class="gm-content">
            <section class="gm-list" aria-labelledby="gm-list-title">
                <div class="gm-list-heading"><h2 id="gm-list-title">Listado de Maestros</h2><p>Información, asignaciones y acceso institucional.</p></div>
                <table class="gm-table">
                    <caption class="gm-sr-only">Maestros registrados y acciones administrativas</caption>
                    <colgroup><col class="gm-col-code"><col class="gm-col-name"><col><col class="gm-col-state"><col class="gm-col-actions"></colgroup>
                    <thead><tr><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Materias asignadas</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                    @forelse($maestros as $maestro)
                        <tr class="fila-maestro" data-nombre="{{ strtolower($maestro->nombre_completo) }}">
                            <td data-label="Código"><span class="gm-code">{{ $maestro->codigo }}</span></td>
                            <td data-label="Nombre"><div class="gm-identity"><span class="gm-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($maestro->nombre,0,1).mb_substr($maestro->apellido,0,1)) }}</span><div><p class="gm-name">{{ $maestro->nombre_completo }}</p><p class="gm-assignment-count">{{ $maestro->detalleCursos->count() }} {{ $maestro->detalleCursos->count() === 1 ? 'asignación' : 'asignaciones' }}</p></div></div></td>
                            <td data-label="Materias asignadas"><div class="gm-subjects">
                                @forelse($maestro->detalleCursos->take(3) as $detalle)<span class="gm-subject">{{ $detalle->materia->nombre ?? '—' }}</span>
                                @empty<span class="gm-muted">Sin asignaciones</span>@endforelse
                                @if($maestro->detalleCursos->count() > 3)<span class="gm-subject gm-more">+{{ $maestro->detalleCursos->count() - 3 }} más</span>@endif
                            </div></td>
                            <td data-label="Estado"><span class="gm-status {{ $maestro->activo ? 'gm-active' : 'gm-inactive' }}">{{ $maestro->activo ? 'Activo' : 'Inactivo' }}</span></td>
                            <td data-label="Acciones"><div class="gm-actions">
                                <a class="gm-button gm-primary" href="{{ route('admin.maestros.show', $maestro->id) }}">Gestionar programas</a>
                                <button type="button" class="gm-button gm-secondary" id="gm-trigger-{{ $maestro->id }}" data-gm-trigger aria-haspopup="menu" aria-expanded="false" aria-controls="gm-menu-{{ $maestro->id }}">Más acciones <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg><span class="gm-sr-only"> de {{ $maestro->nombre_completo }}</span></button>
                                <div popover="auto" id="gm-menu-{{ $maestro->id }}" class="gm-menu" data-gm-menu role="menu" aria-labelledby="gm-trigger-{{ $maestro->id }}">
                                    <button type="button" role="menuitem" onclick="abrirModalEditarMaestro(@js($maestro->id), @js($maestro->nombre), @js($maestro->apellido), @js($maestro->telefono), @js($maestro->dui), @js($maestro->codigo), @js($maestro->user?->email ?? ''))">Editar</button>
                                    <a role="menuitem" href="{{ route('admin.personas.estado',['maestros', $maestro->id]) }}">Estado y acceso</a>
                                </div>
                            </div></td>
                        </tr>
                    @empty<tr><td colspan="5" class="gm-empty">No hay maestros registrados.</td></tr>@endforelse
                    </tbody>
                </table>
                <div id="sinResultados" class="gm-empty" style="display:none;">No se encontraron maestros para tu búsqueda.</div>
            </section>
        </div>
    </div>
</div>
@include('admin.partials.modal-nuevo-maestro')
@include('admin.partials.modal-editar-maestro')
@push('scripts')
@vite('resources/js/admin/maestros.js')
<script>{!! file_get_contents(resource_path('js/admin/maestros-listado.js')) !!}</script>
@endpush
<x-logout-modal />
</x-app-layout>
<x-app-layout>
@push('styles')
<style>{!! file_get_contents(resource_path('css/admin/estado-persona.css')) !!}</style>
@endpush
@php
    $esAlumno = $tipo === 'alumnos';
    $accion = $persona->activo ? 'Desactivar' : 'Reactivar';
@endphp
<div class="estado-persona">
@include('components.admin-sidebar', ['active' => $tipo])
<main class="ep-main">
    <header class="ep-heading">
        <div><p class="ep-eyebrow">Administración · ITM Aguilares</p><h1>Estado y acceso institucional</h1><p class="ep-muted">Gestiona el acceso y consulta su historial de cambios.</p></div>
        <x-boton-regreso :href="$esAlumno ? route('admin.alumnos') : route('admin.maestros')">{{ $esAlumno ? 'Volver a Alumnos' : 'Volver a Maestros' }}</x-boton-regreso>
    </header>
    @if(session('success'))<div class="ep-notice ep-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="ep-notice ep-error" role="alert" tabindex="-1"><strong>No se pudo completar la operación.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="ep-overview">
        <section class="ep-card" aria-labelledby="ep-person-title">
            <div class="ep-section-heading"><h2 id="ep-person-title">Información de la persona</h2></div>
            <div class="ep-identity">
                <span class="ep-person-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></span>
                <div><p class="ep-eyebrow">{{ $esAlumno ? 'Alumno' : 'Maestro' }}</p><h3>{{ $persona->nombre_completo }}</h3></div>
            </div>
            <dl class="ep-facts"><div><dt>Código institucional</dt><dd>{{ $persona->codigo ?: 'Sin código registrado' }}</dd></div><div><dt>Estado actual</dt><dd><span class="ep-badge {{ $persona->activo ? 'ep-active' : 'ep-inactive' }}">{{ $persona->activo ? 'Activo' : 'Inactivo' }}</span></dd></div></dl>
            <p class="ep-muted">{{ $persona->activo ? 'El expediente está activo. El acceso requiere una cuenta válida y los permisos correspondientes.' : 'El acceso institucional está bloqueado. El expediente y su historial se conservan.' }}</p>
            @if(!$persona->user_id)<div class="ep-notice ep-warning ep-account" role="status"><strong>Expediente sin cuenta asociada.</strong> Puedes gestionar su estado; esta acción no crea una cuenta.</div>@endif
            <div class="ep-preservation"><strong>El historial se conserva</strong><p>Matrículas, cupos, notas, asistencias, mensualidades, pagos y documentos permanecen intactos. Una matrícula aprobada sigue contando para cupos y los procesos actuales de mensualidades.</p></div>
        </section>
        <section class="ep-card" aria-labelledby="ep-access-title">
            <div class="ep-section-heading"><h2 id="ep-access-title">Administración del acceso</h2><p class="ep-muted">{{ $accion }} el acceso de esta persona.</p></div>
            <div class="ep-notice {{ $persona->activo ? 'ep-warning' : 'ep-info' }}">
                @if($persona->activo)<strong>La desactivación bloquea el acceso y las sesiones abiertas.</strong><p>La matrícula se mantiene independiente de este cambio.</p>
                @else<strong>La reactivación exige un nuevo inicio de sesión.</strong><p>No restablece matrículas ni asignaciones finalizadas.</p>@endif
            </div>
            @if($persona->activo && $asignaciones->isNotEmpty())<p class="ep-notice ep-warning" id="ep-assignment-warning">Antes de desactivar, resuelve las asignaciones activas y programadas que se muestran a continuación.</p>@endif
            <form method="POST" action="{{ route('admin.personas.estado.update',[$tipo,$persona->id]) }}" data-persona-form data-persona-accion="{{ $persona->activo ? 'desactivar' : 'reactivar' }}" data-persona-nombre="{{ $persona->nombre_completo }}" data-persona-confirm="¿{{ $accion }} el acceso de {{ $persona->nombre_completo }}? No cambiará matrículas ni registros históricos.">
                @csrf @method('PATCH')
                <input type="hidden" name="esperado" value="{{ $persona->activo ? 1 : 0 }}">
                <input type="hidden" name="revision" value="{{ $revision }}">
                <input type="hidden" name="activo" value="{{ $persona->activo ? 0 : 1 }}">
                <label for="motivo-estado">Motivo del cambio de estado <span class="ep-required">(obligatorio)</span></label>
                <textarea id="motivo-estado" name="motivo" required minlength="10" maxlength="500" rows="5" data-persona-reason aria-describedby="motivo-estado-ayuda motivo-estado-contador{{ $errors->has('motivo') ? ' motivo-estado-error' : '' }}" @if($errors->has('motivo')) aria-invalid="true" @endif placeholder="Describe el motivo administrativo de este cambio.">{{ old('motivo') }}</textarea>
                <div class="ep-field-meta"><span id="motivo-estado-ayuda">Entre 10 y 500 caracteres. Solo administración puede consultar el motivo.</span><span id="motivo-estado-contador" data-persona-counter aria-live="polite">{{ mb_strlen(old('motivo', '')) }} / 500</span></div>
                @error('motivo')<p class="ep-field-error" id="motivo-estado-error">{{ $message }}</p>@enderror
                <div class="ep-form-actions"><span class="ep-processing" data-persona-status role="status" aria-live="polite"></span><button class="ep-button {{ $persona->activo ? 'ep-button-danger' : '' }}" type="submit" @disabled($persona->activo && $asignaciones->isNotEmpty()) @if($persona->activo && $asignaciones->isNotEmpty()) aria-describedby="ep-assignment-warning" @endif><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span data-persona-button-label>{{ $accion }} acceso</span></button></div>
            </form>
        </section>
    </div>
    @if($asignaciones->isNotEmpty())
    <section class="ep-card ep-section" aria-labelledby="ep-assignments-title">
        <div class="ep-section-heading"><h2 id="ep-assignments-title">Asignaciones por resolver</h2><p class="ep-muted">Revisa las asignaciones antes de desactivar al maestro.</p><a class="ep-link" href="{{ route('admin.maestros.show',$persona->id) }}">Gestionar sustitución o desasignación de programas activos →</a></div>
        <div class="ep-assignments">
        @foreach($asignaciones as $asignacion)
            <article class="ep-assignment">
                <div class="ep-assignment-title"><h3>#{{ $asignacion->id }} · {{ $asignacion->detalleCurso?->curso?->nombre ?? 'Curso no disponible' }}</h3><span class="ep-badge ep-neutral">{{ ucfirst($asignacion->estado) }}</span></div>
                <p class="ep-muted">{{ $asignacion->detalleCurso?->materia?->nombre ?? 'Materia no disponible' }}</p>
                @if($asignacion->estado === 'programada')
                <form method="POST" action="{{ route('admin.asignaciones.finalizar-programada',$asignacion->id) }}" data-persona-form data-persona-accion="finalizar" data-persona-nombre="{{ $persona->nombre_completo }}" data-persona-confirm="¿Finalizar la asignación programada #{{ $asignacion->id }} de {{ $persona->nombre_completo }} conservando su historial y fechas?">
                    @csrf @method('PATCH')
                    <label for="motivo-{{ $asignacion->id }}">Motivo de finalización</label>
                    <textarea id="motivo-{{ $asignacion->id }}" name="motivo" required minlength="10" maxlength="500" rows="3" data-persona-reason aria-describedby="ayuda-{{ $asignacion->id }} contador-{{ $asignacion->id }}"></textarea>
                    <div class="ep-field-meta"><span id="ayuda-{{ $asignacion->id }}">Entre 10 y 500 caracteres.</span><span id="contador-{{ $asignacion->id }}" data-persona-counter aria-live="polite">0 / 500</span></div>
                    <div class="ep-form-actions"><span class="ep-processing" data-persona-status role="status" aria-live="polite"></span><button type="submit" class="ep-button ep-button-secondary"><span data-persona-button-label>Finalizar asignación programada</span></button></div>
                </form>
                @endif
            </article>
        @endforeach
        </div>
    </section>
    @endif
    <section class="ep-card ep-section" aria-labelledby="ep-history-title">
        <div class="ep-section-heading"><h2 id="ep-history-title">Historial de cambios</h2><p class="ep-muted">Registro de estados y motivos · Solo administración</p></div>
        <div class="ep-table-scroll" tabindex="0" role="region" aria-labelledby="ep-history-title">
        <table class="ep-table"><caption class="ep-sr-only">Historial de cambios de estado de {{ $persona->nombre_completo }}</caption>
            <thead><tr><th scope="col">Fecha y hora</th><th scope="col">Estado anterior</th><th scope="col">Estado nuevo</th><th scope="col">Administrador responsable</th><th scope="col">Motivo registrado</th></tr></thead>
            <tbody>@forelse($historial as $evento)
            <tr><td class="ep-date">{{ $evento->registrado_at->format('d/m/Y H:i') }}</td><td><span class="ep-badge {{ $evento->estado_anterior ? 'ep-active' : 'ep-inactive' }}">{{ $evento->estado_anterior ? 'Activo' : 'Inactivo' }}</span></td><td><span class="ep-badge {{ $evento->estado_nuevo ? 'ep-active' : 'ep-inactive' }}">{{ $evento->estado_nuevo ? 'Activo' : 'Inactivo' }}</span></td><td>{{ $evento->administrador?->name }}</td><td class="ep-reason">{{ $evento->motivo }}</td></tr>
            @empty<tr><td colspan="5"><div class="ep-empty"><strong>Sin cambios de estado registrados.</strong><p>Los próximos cambios aparecerán aquí con su fecha, responsable y motivo.</p></div></td></tr>@endforelse</tbody>
        </table></div>
        <div class="ep-pagination">{{ $historial->links() }}</div>
        @if($cierres)
        <div class="ep-section-heading ep-closures-heading"><h3 id="ep-closures-title">Finalizaciones de asignaciones programadas</h3></div>
        <div class="ep-table-scroll" tabindex="0" role="region" aria-labelledby="ep-closures-title"><table class="ep-table"><thead><tr><th scope="col">Asignación</th><th scope="col">Fecha y hora</th><th scope="col">Responsable</th><th scope="col">Motivo registrado</th></tr></thead><tbody>
            @forelse($cierres as $cierre)<tr><td>#{{ $cierre->asignacion_id }}</td><td>{{ $cierre->registrado_at }}</td><td>{{ $cierre->responsable }}</td><td class="ep-reason">{{ $cierre->motivo }}</td></tr>
            @empty<tr><td colspan="4"><div class="ep-empty">Sin finalizaciones registradas.</div></td></tr>@endforelse
        </tbody></table></div>
        <div class="ep-pagination">{{ $cierres->links() }}</div>
        @endif
    </section>
</main></div>
@push('scripts')
<script>{!! file_get_contents(resource_path('js/admin/estado-persona.js')) !!}</script>
@endpush
</x-app-layout>

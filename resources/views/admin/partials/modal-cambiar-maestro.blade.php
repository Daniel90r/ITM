@php
    $detalleContextoId = old('detalle_id_contexto');
    $rutaCambio = '';
    $debeAbrir = $errors->has('nuevo_maestro_id') || $errors->has('motivo_cambio');

    $detalleContexto = null;
    if ($detalleContextoId) {
        $detalleContexto = $maestro->detalleCursos->firstWhere('id', $detalleContextoId);
        if ($detalleContexto && $detalleContexto->maestro_id == $maestro->id) {
            $rutaCambio = route('admin.maestros.asignaciones.cambiar', [$maestro->id, $detalleContextoId]);
        }
    }
@endphp

<div id="modalCambiarMaestro"
    data-open="{{ ($debeAbrir && $rutaCambio !== '') ? 1 : 0 }}"
    data-action="{{ $rutaCambio }}"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:520px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px; max-height:90vh; overflow-y:auto;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Cambiar maestro del programa</p>
            </div>
            <button type="button" onclick="cerrarModalCambiarMaestro()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:14px; color:#6b7280; display:flex; align-items:center; justify-content:center;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        @if($rutaCambio)
        <div style="background:#fffbeb; border:1px solid #fbbf24; border-radius:10px; padding:12px 16px; margin-bottom:16px;">
            <p style="font-size:11px; color:#92400e; margin:0 0 4px;">Curso: <span id="contextoCurso" style="font-weight:600;">{{ $detalleContexto?->curso?->nombre ?? '—' }}</span></p>
            <p style="font-size:11px; color:#92400e; margin:0;">Programa: <span id="contextoPrograma" style="font-weight:600;">{{ $detalleContexto?->materia?->nombre ?? '—' }}</span></p>
            <p style="font-size:11px; color:#92400e; margin:4px 0 0; font-style:italic;">Este cambio es permanente. Una sustitución por un solo día debe registrarse mediante el flujo de asistencia.</p>
        </div>
        @endif

        <form id="formCambiarMaestro" method="POST" action="{{ $rutaCambio }}" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
            @csrf
            @method('PUT')
            <input type="hidden" name="detalle_id_contexto" id="detalleIdContexto" value="{{ old('detalle_id_contexto', '') }}">

            <div>
                <label for="nuevo_maestro_id" style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nuevo maestro <span style="color:#dc2626;">*</span></label>
                <select id="nuevo_maestro_id" name="nuevo_maestro_id" required
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                    onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'"
                    {{ $maestrosDisponibles->isEmpty() ? 'disabled' : '' }}>
                    <option value="">Seleccionar maestro...</option>
                    @foreach($maestrosDisponibles as $dispMaestro)
                    <option value="{{ $dispMaestro->id }}" {{ old('nuevo_maestro_id') == $dispMaestro->id ? 'selected' : '' }}>
                        {{ $dispMaestro->nombre_completo }}
                    </option>
                    @endforeach
                </select>
                @error('nuevo_maestro_id')
                <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="motivo_cambio" style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Motivo del cambio <span style="color:#dc2626;">*</span></label>
                <textarea id="motivo_cambio" name="motivo_cambio" required minlength="10" maxlength="255"
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; min-height:80px; resize:vertical;"
                    onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">{{ old('motivo_cambio') }}</textarea>
                @error('motivo_cambio')
                <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="display:flex; gap:10px; padding-top:4px;">
                <button type="button" onclick="cerrarModalCambiarMaestro()"
                    style="flex:1; padding:10px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="submit"
                    style="flex:1; padding:10px; background:#111827; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#ffffff; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'"
                    {{ ($maestrosDisponibles->isEmpty() || !$rutaCambio) ? 'disabled' : '' }}>
                    Confirmar cambio
                </button>
            </div>
        </form>
    </div>
</div>

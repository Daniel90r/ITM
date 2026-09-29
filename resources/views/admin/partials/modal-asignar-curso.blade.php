@php
    $debeAbrirModal = $errors->has('curso_id') || $errors->has('materia_ids.*') || $errors->has('materia_ids');
@endphp

<div id="modalAsignarCurso" data-open="{{ $debeAbrirModal ? 1 : 0 }}" data-maestro-id="{{ $maestro->id }}" data-materias-seleccionadas="{{ json_encode(old('materia_ids', [])) }}" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:520px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px; max-height:90vh; overflow-y:auto;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Asignar programas al maestro</p>
            </div>
            <button type="button" onclick="cerrarModalAsignarCurso()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:14px; color:#6b7280; display:flex; align-items:center; justify-content:center;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        @if($cursos->isEmpty())
            <div style="background:#fffbeb; border:1px solid #fbbf24; border-radius:10px; padding:12px 16px; margin-bottom:16px;">
                <p style="font-size:12px; color:#92400e; margin:0;">
                    No hay cursos activos disponibles. Active cursos antes de asignar un programa.
                </p>
            </div>
        @endif

        <form id="formAsignarCurso" method="POST" action="{{ route('admin.maestros.asignar', $maestro->id) }}" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
            @csrf

            <div>
                <label for="curso_id" style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Curso <span style="color:#dc2626;">*</span></label>
                <select id="curso_id" name="curso_id"
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                    required
                    {{ $cursos->isEmpty() ? 'disabled' : '' }}>
                    <option value="">Seleccionar curso...</option>
                    @foreach($cursos as $curso)
                    <option value="{{ $curso->id }}" {{ old('curso_id') == $curso->id ? 'selected' : '' }}>
                        {{ $curso->nombre }} — {{ $curso->nivel }}{{ $curso->seccion ? ' / ' . $curso->seccion : '' }} ({{ $curso->anio_lectivo }})
                    </option>
                    @endforeach
                </select>
                @error('curso_id')
                <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Programas / Materias <span style="color:#dc2626;">*</span></label>
                <div id="materiasContainer" style="display:none;">
                    <div id="materiasList" style="max-height:200px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; padding:10px;">
                        <p style="font-size:12px; color:#6b7280; text-align:center; padding:20px;">Seleccione un curso para ver las materias disponibles</p>
                    </div>
                    @error('materia_ids.*')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                    @error('materia_ids')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div style="display:flex; gap:10px; padding-top:4px;">
                <button type="button" onclick="cerrarModalAsignarCurso()"
                    style="flex:1; padding:10px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="submit" id="btnAsignar"
                    style="flex:1; padding:10px; background:#111827; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#ffffff; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'"
                    {{ $cursos->isEmpty() ? 'disabled' : '' }}>
                    Asignar seleccionadas
                </button>
            </div>
        </form>
    </div>
</div>

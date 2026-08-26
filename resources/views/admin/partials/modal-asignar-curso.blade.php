{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- MODAL: ASIGNAR CURSO A MAESTRO --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div id="modalAsignarCurso" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:520px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px; max-height:90vh; overflow-y:auto;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;" id="tituloModalAsignar">Asignar curso a Maestro</p>
            </div>
            <button onclick="cerrarModalAsignarCurso()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:14px; color:#6b7280; display:flex; align-items:center; justify-content:center;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        <form id="formAsignarCurso" method="POST" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
            @csrf
            <select name="curso_id" required
                style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                <option value="">Seleccionar curso...</option>
                @foreach($cursos as $curso)
                <option value="{{ $curso->id }}">{{ $curso->nombre }} — {{ $curso->nivel }}</option>
                @endforeach
            </select>

            <div style="display:flex; gap:10px; padding-top:4px;">
                <button type="button" onclick="cerrarModalAsignarCurso()"
                    style="flex:1; padding:10px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="submit"
                    style="flex:1; padding:10px; background:#111827; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#ffffff; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                    Asignar
                </button>
            </div>
        </form>
    </div>
</div>

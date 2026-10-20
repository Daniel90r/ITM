{{-- Aqui generamos un nuevo curso --}}
<div id="modalNuevoCurso" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div class="curso-form-modal" style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:480px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Nuevo Curso</p>
            </div>
            <button onclick="cerrarModalCurso()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:14px; color:#6b7280; display:flex; align-items:center; justify-content:center;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.cursos.store') }}" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
            @csrf

            {{-- Nombre de curso y Sección --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nombre <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="nombre" required maxlength="100" placeholder="Ej: Curso A"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Sección <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="seccion" required placeholder="Ej: A, B, C"
                        maxlength="2"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; text-transform:uppercase; font-family:monospace;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'; this.value=this.value.toUpperCase()">
                </div>
            </div>

            {{-- El turno y año --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Turno <span style="color:#dc2626;">*</span></label>
                    <select name="nivel" required
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                        <option value="">Seleccionar...</option>
                        <option value="Matutino">Matutino</option>
                        <option value="Vespertino">Vespertino</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Año lectivo <span style="color:#dc2626;">*</span></label>
                    <input type="number" name="anio_lectivo" required value="{{ date('Y') }}"
                        min="2020" max="2099"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; grid-column: span 2;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Fecha de inicio <span style="color:#dc2626;">*</span></label>
                        <input type="date" name="fecha_inicio" required
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Fecha de finalización <span style="color:#dc2626;">*</span></label>
                        <input type="date" name="fecha_fin" required
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>
             </div>

             {{-- Botones --}}
            @include('admin.partials.horario-semanal', ['curso' => null])
            <div style="display:flex; gap:10px; padding-top:4px;">
                <button type="button" onclick="cerrarModalCurso()"
                    style="flex:1; padding:10px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="submit"
                    style="flex:1; padding:10px; background:#111827; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#ffffff; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                    Guardar curso
                </button>
            </div>
        </form>
    </div>
</div>

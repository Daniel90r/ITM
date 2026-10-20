<div id="modalEditarAlumno" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:540px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px; max-height:90vh; overflow-y:auto;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#3b82f6; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Editar Alumno</p>
            </div>
            <button onclick="cerrarModalEditar()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:14px; color:#6b7280; display:flex; align-items:center; justify-content:center;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        <form id="formEditarAlumno" method="POST" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
            @csrf
            @method('PUT')
            <input type="hidden" id="editId">

            {{-- Nombre y Apellido --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nombre Completo <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="editNombre" name="nombre" required maxlength="100"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Apellido Completo <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="editApellido" name="apellido" required maxlength="100"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
            </div>

            {{-- Teléfono --}}
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Teléfono</label>
                <input type="text" id="editTelefono" name="telefono"
                    data-phone-mask
                    maxlength="9" inputmode="numeric"
                    placeholder="Ej: 7777-8888"
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
            </div>

            {{-- Curso (solo lectura) --}}
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Curso / Sección</label>
                <input type="text" id="editCursoDisplay" readonly
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;">
            </div>

            {{-- Separador cuenta --}}
            <div style="border-top:1px solid #e5e7eb; padding-top:16px;">
                <p style="font-size:12px; font-weight:600; color:#374151; margin:0 0 14px; display:flex; align-items:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Cuenta de acceso
                </p>

                {{-- Email --}}
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Correo electrónico <span style="color:#dc2626;">*</span></label>
                    <input type="email" id="editEmail" name="email" required
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                </div>

                {{-- Nueva contraseña --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                            Nueva contraseña
                            <span style="color:#9ca3af; font-weight:400;">(opcional)</span>
                        </label>
                        <input type="password" class="campo-secreto" id="editPassword" name="password"
                            placeholder="Dejar vacío para no cambiar"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Confirmar contraseña</label>
                        <input type="password" class="campo-secreto" name="password_confirmation"
                            placeholder="Repetir nueva contraseña"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>
                <p style="font-size:11px; color:#9ca3af; margin:6px 0 0;">Si no deseas cambiar la contraseña deja los campos vacíos.</p>
            </div>

            {{-- Botones --}}
            <div style="display:flex; gap:10px; padding-top:4px;">
                <button type="button" onclick="cerrarModalEditar()"
                    style="flex:1; padding:10px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="submit"
                    style="flex:1; padding:10px; background:#3b82f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#ffffff; cursor:pointer;"
                    onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

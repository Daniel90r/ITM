<div id="modalEditarMaestro" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:520px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px; max-height:90vh; overflow-y:auto;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Editar Maestro</p>
            </div>
            <button type="button" onclick="cerrarModalEditarMaestro()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:14px; color:#6b7280; display:flex; align-items:center; justify-content:center;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        <form id="formEditarMaestro" method="POST" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
            @csrf
            @method('PUT')
            <input type="hidden" id="editIdMaestro" name="maestro_id">

            {{-- Nombre y Apellido --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nombres <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="editNombre" name="nombre" required
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('nombre', 'updateMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Apellidos <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="editApellido" name="apellido" required
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('apellido', 'updateMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Teléfono y DUI --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Teléfono <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="editTelefono" name="telefono" required maxlength="9"
                        inputmode="numeric" placeholder="Ej: 7123-4567"
                        pattern="[267][0-9]{3}-[0-9]{4}" autocomplete="tel"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('telefono', 'updateMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">DUI <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="editDui" name="dui" required maxlength="10"
                        inputmode="numeric" placeholder="Ej: 01234567-8"
                        pattern="[0-9]{8}-[0-9]" autocomplete="off"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('dui', 'updateMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Código --}}
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Código <span style="color:#9ca3af; font-weight:400;">(No editable)</span></label>
                <input type="text" id="editCodigo" name="codigo" readonly
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f3f4f6; color:#6b7280; outline:none; box-sizing:border-box; font-family:monospace;">
                @error('codigo', 'updateMaestro')
                <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                @enderror
            </div>

            {{-- Separador cuenta --}}
            <div style="border-top:1px solid #e5e7eb; padding-top:16px;">
                <p style="font-size:12px; font-weight:600; color:#374151; margin:0 0 14px; display:flex; align-items:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Cuenta de acceso al sistema
                </p>

                {{-- Email --}}
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Correo electrónico <span style="color:#dc2626;">*</span></label>
                    <input type="email" id="editEmail" name="email" required
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('email', 'updateMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Nueva contraseña --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                            Nueva contraseña
                            <span style="color:#9ca3af; font-weight:400;">(opcional)</span>
                        </label>
                        <input type="password" class="campo-secreto" id="editPassword" name="password" autocomplete="new-password"
                            placeholder="Dejar vacío para no cambiar"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#111827'">
                        @error('password', 'updateMaestro')
                        <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Confirmar contraseña</label>
                        <input type="password" class="campo-secreto" id="editPasswordConfirmation" name="password_confirmation" autocomplete="new-password"
                            placeholder="Repetir nueva contraseña"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#111827'">
                    </div>
                </div>
            </div>

            {{-- Botones --}}
            <div style="display:flex; gap:10px; padding-top:4px;">
                <button type="button" onclick="cerrarModalEditarMaestro()"
                    style="flex:1; padding:10px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="submit"
                    style="flex:1; padding:10px; background:#111827; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#ffffff; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

@if($errors->updateMaestro->isNotEmpty())
<script>
    window.addEventListener('load', function() {
        abrirModalEditarMaestro(
            @js(old('maestro_id')),
            @js(old('nombre')),
            @js(old('apellido')),
            @js(old('telefono')),
            @js(old('dui')),
            @js(old('codigo')),
            @js(old('email'))
        );
    });
</script>
@endif

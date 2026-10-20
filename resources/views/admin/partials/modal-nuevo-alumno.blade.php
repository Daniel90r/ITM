{{-- Aqui generamos un nuevo alumno --}}
<div id="modalNuevoAlumno" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:560px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px; max-height:90vh; overflow-y:auto;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#3b82f6; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Nuevo Alumno</p>
            </div>
            <button onclick="cerrarModalNuevo()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:14px; color:#6b7280; display:flex; align-items:center; justify-content:center;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        {{-- Errores de validación (solo si vienen de este formulario) --}}
        @if($errors->any() && old('form_origen') === 'nuevo_alumno')
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 14px; margin-bottom:16px;">
                <p style="font-size:12px; font-weight:700; color:#dc2626; margin:0 0 6px;">Revisa los siguientes campos:</p>
                <ul style="margin:0; padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li style="font-size:12px; color:#b91c1c;">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.alumnos.store') }}" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
            @csrf
            <input type="hidden" name="form_origen" value="nuevo_alumno">

            {{-- Fila 1: Nombre y Apellido --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nombre <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="nombre" id="nombreNuevoAlumno" required maxlength="100" placeholder="Ej: Juan Carlos" value="{{ old('nombre') }}"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Apellido <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="apellido" id="apellidoNuevoAlumno" required maxlength="100" placeholder="Ej: Pérez García" value="{{ old('apellido') }}"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
            </div>

            {{-- Fila 2: Código y Fecha nacimiento --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Código / Matrícula <span style="color:#dc2626;">*</span></label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="text" name="codigo" id="codigoAlumno" required placeholder="Se genera automáticamente" readonly
                            style="flex:1; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f0fdf4; color:#111827; outline:none; box-sizing:border-box; font-family:monospace;">
                        <span id="badgeCodigo" style="display:none; background:#dcfce7; color:#16a34a; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap;">Automático</span>
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Fecha de nacimiento <span style="color:#dc2626;">*</span></label>
                    <input type="date" name="fecha_nacimiento" id="fechaNacNuevoAlumno" required value="{{ old('fecha_nacimiento') }}"
                        onchange="evaluarEdadNuevoAlumno()"
                        max="{{ now()->subYears(14)->toDateString() }}"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    <p id="fechaNacError" style="display:none; font-size:11px; color:#dc2626; margin:6px 0 0;"></p>
                </div>
            </div>

            {{-- Indicador de edad --}}
            <div id="badgeEdadAlumno" style="display:none; align-items:center; gap:8px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:10px 14px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <p id="badgeEdadTexto" style="font-size:12px; font-weight:600; color:#1d4ed8; margin:0;"></p>
            </div>

            {{-- Fila 3: Sexo y Teléfono --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Sexo</label>
                    <select name="sexo"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                        <option value="">Seleccionar...</option>
                        <option value="M" @selected(old('sexo') === 'M')>Masculino</option>
                        <option value="F" @selected(old('sexo') === 'F')>Femenino</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Teléfono</label>
                    <input type="text" name="telefono" id="telefonoNuevoAlumno" placeholder="Ej: 7777-8888" value="{{ old('telefono') }}"
                        data-phone-mask
                        maxlength="9" inputmode="numeric"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
            </div>

            {{-- Dirección --}}
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Dirección</label>
                <input type="text" name="direccion" placeholder="Ej: Calle Principal #123, Aguilares" value="{{ old('direccion') }}"
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- SECCIÓN: DUI DEL ALUMNO (solo mayor de edad) --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div id="seccionDuiAlumno" style="display:none; border-top:1px solid #e5e7eb; padding-top:16px;">
                <p style="font-size:12px; font-weight:600; color:#374151; margin:0 0 14px; display:flex; align-items:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    Documento de identidad
                </p>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">DUI del alumno <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="dui" id="duiAlumno" placeholder="Ej: 01234567-8" maxlength="10" value="{{ old('dui') }}"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; font-family:monospace;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    <p style="font-size:11px; color:#6b7280; margin:6px 0 0;">Al ser mayor de edad, el alumno no requiere encargado.</p>
                </div>
            </div>

            {{-- ─────────────────────────────────────────────── --}}
            {{-- SECCIÓN: ENCARGADO (solo menor de edad) --}}
            {{-- ─────────────────────────────────────────────── --}}
            <div id="seccionEncargado" style="display:none; border-top:1px solid #e5e7eb; padding-top:16px;">
                <p style="font-size:12px; font-weight:600; color:#374151; margin:0 0 14px; display:flex; align-items:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Datos del encargado
                </p>

                {{-- Encargado: Nombre y Apellido --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nombre <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="encargado_nombre" id="encNombre" placeholder="Ej: María Elena" value="{{ old('encargado_nombre') }}"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Apellido <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="encargado_apellido" id="encApellido" placeholder="Ej: García de Pérez" value="{{ old('encargado_apellido') }}"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>

                {{-- Encargado: Parentesco y DUI --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Parentesco <span style="color:#dc2626;">*</span></label>
                        <select name="encargado_parentesco" id="encParentesco"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                            <option value="">Seleccionar...</option>
                            <option value="Padre" @selected(old('encargado_parentesco') === 'Padre')>Padre</option>
                            <option value="Madre" @selected(old('encargado_parentesco') === 'Madre')>Madre</option>
                            <option value="Tutor" @selected(old('encargado_parentesco') === 'Tutor')>Tutor</option>
                            <option value="Abuelo/a" @selected(old('encargado_parentesco') === 'Abuelo/a')>Abuelo/a</option>
                            <option value="Tío/a" @selected(old('encargado_parentesco') === 'Tío/a')>Tío/a</option>
                            <option value="Hermano/a" @selected(old('encargado_parentesco') === 'Hermano/a')>Hermano/a</option>
                            <option value="Otro" @selected(old('encargado_parentesco') === 'Otro')>Otro</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">DUI del encargado <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="encargado_dui" id="encDui" placeholder="Ej: 01234567-8" maxlength="10" value="{{ old('encargado_dui') }}"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; font-family:monospace;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>

                {{-- Encargado: Teléfono y Email --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Teléfono</label>
                        <input type="text" name="encargado_telefono" id="encargadoTelefono" placeholder="Ej: 7777-8888" value="{{ old('encargado_telefono') }}"
                            data-phone-mask
                            maxlength="9" inputmode="numeric"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Correo electrónico</label>
                        <input type="email" name="encargado_email" placeholder="encargado@correo.com" value="{{ old('encargado_email') }}"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>

                <p style="font-size:11px; color:#6b7280; margin:10px 0 0;">Si el DUI del encargado ya está registrado (por ejemplo, un hermano del alumno), se reutilizará el mismo encargado.</p>
            </div>

            {{-- Separador cuenta de acceso --}}
            <div style="border-top:1px solid #e5e7eb; padding-top:16px;">
                <p style="font-size:12px; font-weight:600; color:#374151; margin:0 0 14px; display:flex; align-items:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Cuenta de acceso al portal
                </p>

                {{-- Email --}}
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Correo electrónico <span style="color:#dc2626;">*</span></label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="email" name="email" id="emailAlumno" required placeholder="Ej: alumno@gmail.com"
                            style="flex:1; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>

                {{-- Contraseña --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Contraseña <span style="color:#dc2626;">*</span></label>
                        <input type="text" class="campo-secreto" name="password" required placeholder="Mínimo 8 caracteres"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Confirmar contraseña <span style="color:#dc2626;">*</span></label>
                        <input type="text" class="campo-secreto" name="password_confirmation" required placeholder="Repetir contraseña"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>
            </div>

            {{-- Botones --}}
            <div style="display:flex; gap:10px; padding-top:4px;">
                <button type="button" onclick="cerrarModalNuevo()"
                    style="padding:10px 16px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="button" onclick="limpiarFormularioAlumno()"
                    style="padding:10px 16px; background:#ffffff; border:1px solid #e5e7eb; border-radius:10px; font-size:13px; font-weight:600; color:#6b7280; cursor:pointer;"
                    onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#ffffff'">
                    Limpiar
                </button>
                <button type="submit" id="btnGuardarNuevoAlumno"
                    style="flex:1; padding:10px; background:#3b82f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#ffffff; cursor:pointer;"
                    onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                    Guardar alumno
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ═══════════════════════════════════════════════════════════
    // LÓGICA: mayor / menor de edad en modal Nuevo Alumno
    // ═══════════════════════════════════════════════════════════
    function evaluarEdadNuevoAlumno() {
        const input      = document.getElementById('fechaNacNuevoAlumno');
        const badge      = document.getElementById('badgeEdadAlumno');
        const badgeTexto = document.getElementById('badgeEdadTexto');
        const secDui     = document.getElementById('seccionDuiAlumno');
        const secEnc     = document.getElementById('seccionEncargado');

        const duiAlumno  = document.getElementById('duiAlumno');
        const encNombre  = document.getElementById('encNombre');
        const encApellido   = document.getElementById('encApellido');
        const encParentesco = document.getElementById('encParentesco');
        const encDui        = document.getElementById('encDui');
        const fechaError = document.getElementById('fechaNacError');
        const btnGuardar = document.getElementById('btnGuardarNuevoAlumno');

        // Sin fecha: ocultar todo
        if (!input.value) {
            badge.style.display  = 'none';
            secDui.style.display = 'none';
            secEnc.style.display = 'none';
            duiAlumno.required = false;
            encNombre.required = encApellido.required = encParentesco.required = encDui.required = false;
            if (fechaError) { fechaError.style.display = 'none'; fechaError.textContent = ''; }
            if (btnGuardar) btnGuardar.disabled = false;
            return;
        }

        // Calcular edad
        const hoy = new Date();
        const fn  = new Date(input.value + 'T00:00:00');
        let edad  = hoy.getFullYear() - fn.getFullYear();
        const m   = hoy.getMonth() - fn.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < fn.getDate())) edad--;

        // Validar edad mínima 14 años
        if (edad < 14) {
            badge.style.display  = 'none';
            secDui.style.display = 'none';
            secEnc.style.display = 'none';
            duiAlumno.required = false;
            encNombre.required = encApellido.required = encParentesco.required = encDui.required = false;
            if (fechaError) {
                fechaError.style.display = 'block';
                fechaError.textContent = 'El alumno debe tener al menos 14 años para poder registrarse.';
            }
            if (btnGuardar) btnGuardar.disabled = true;
            return;
        }

        // Validar fecha futura
        if (fn > hoy) {
            badge.style.display  = 'none';
            secDui.style.display = 'none';
            secEnc.style.display = 'none';
            duiAlumno.required = false;
            encNombre.required = encApellido.required = encParentesco.required = encDui.required = false;
            if (fechaError) {
                fechaError.style.display = 'block';
                fechaError.textContent = 'La fecha de nacimiento debe ser anterior a hoy.';
            }
            if (btnGuardar) btnGuardar.disabled = true;
            return;
        }

        if (fechaError) { fechaError.style.display = 'none'; fechaError.textContent = ''; }
        if (btnGuardar) btnGuardar.disabled = false;

        const esMayor = edad >= 18;

        // Badge informativo
        badge.style.display  = 'flex';
        badgeTexto.textContent = esMayor
            ? 'Mayor de edad (' + edad + ' años) — se solicitará el DUI del alumno.'
            : 'Menor de edad (' + edad + ' años) — se solicitarán los datos del encargado.';

        // Alternar secciones y campos requeridos
        secDui.style.display = esMayor ? 'block' : 'none';
        secEnc.style.display = esMayor ? 'none'  : 'block';

        duiAlumno.required = esMayor;
        encNombre.required = encApellido.required = encParentesco.required = encDui.required = !esMayor;

        // Limpiar la sección que no aplica para no enviar datos residuales
        if (esMayor) {
            encNombre.value = encApellido.value = encDui.value = '';
            encParentesco.value = '';
            document.querySelector('#seccionEncargado [name="encargado_telefono"]').value = '';
            document.querySelector('#seccionEncargado [name="encargado_email"]').value = '';
        } else {
            duiAlumno.value = '';
        }
    }

    // Autoformato de DUI: 01234567-8
    function formatearDui(e) {
        let v = e.target.value.replace(/[^0-9]/g, '').slice(0, 9);
        if (v.length > 8) v = v.slice(0, 8) + '-' + v.slice(8);
        e.target.value = v;
    }

    function formatearDui(e) {
        let v = e.target.value.replace(/[^0-9]/g, '').slice(0, 9);
        if (v.length > 8) v = v.slice(0, 8) + '-' + v.slice(8);
        e.target.value = v;
    }

    function limpiarFormularioAlumno() {
        const form = document.querySelector('#modalNuevoAlumno form');
        if (form) form.reset();

        const badgeEdad = document.getElementById('badgeEdadAlumno');
        const secDui    = document.getElementById('seccionDuiAlumno');
        const secEnc    = document.getElementById('seccionEncargado');

        if (badgeEdad) badgeEdad.style.display = 'none';
        if (secDui) secDui.style.display = 'none';
        if (secEnc) secEnc.style.display = 'none';

        const codigoInput = document.getElementById('codigoAlumno');
        const emailInput  = document.getElementById('emailAlumno');

        if (codigoInput) codigoInput.value = '';
        if (emailInput) emailInput.value = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const duiAlumno = document.getElementById('duiAlumno');
        const encDui    = document.getElementById('encDui');
        if (duiAlumno) duiAlumno.addEventListener('input', formatearDui);
        if (encDui)    encDui.addEventListener('input', formatearDui);

        const nombreInput   = document.getElementById('nombreNuevoAlumno');
        const apellidoInput = document.getElementById('apellidoNuevoAlumno');
        const codigoInput   = document.getElementById('codigoAlumno');
        const badgeCodigo   = document.getElementById('badgeCodigo');

        function limpiar(texto) {
            return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ñ/g, 'n').replace(/Ñ/g, 'N');
        }

        function iniciales(nombre, apellido) {
            const n = limpiar(nombre).trim().split(' ').filter(Boolean);
            const a = limpiar(apellido).trim().split(' ').filter(Boolean);
            let ini = '';
            n.slice(0, 2).forEach(p => { if (p) ini += p[0].toUpperCase(); });
            a.slice(0, 2).forEach(p => { if (p) ini += p[0].toUpperCase(); });
            return ini;
        }

        let debounce;
        async function actualizarCodigoAuto() {
            clearTimeout(debounce);
            debounce = setTimeout(async () => {
                const nom = nombreInput?.value || '';
                const ape = apellidoInput?.value || '';
                const ini = iniciales(nom, ape);

                if (ini.length < 2) {
                    codigoInput.value = '';
                    badgeCodigo.style.display = 'none';
                    return;
                }

                try {
                    const res = await fetch('{{ route("admin.alumnos.siguienteCodigo") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ nombre: nom, apellido: ape })
                    });
                    const data = await res.json();
                    codigoInput.value = data.codigo;
                    badgeCodigo.style.display = 'inline-block';
                } catch (e) {
                    console.error('Error al calcular código:', e);
                }
            }, 300);
        }

        if (nombreInput) nombreInput.addEventListener('input', actualizarCodigoAuto);
        if (apellidoInput) apellidoInput.addEventListener('input', actualizarCodigoAuto);

        // Si hubo errores de validación en este formulario: reabrir modal mostrando la sección correcta
        // (evaluarEdad limpia solo la sección que NO aplica; la que aplica conserva los valores de old())
        @if($errors->any() && old('form_origen') === 'nuevo_alumno')
            evaluarEdadNuevoAlumno();
            actualizarCodigoAuto();
            if (typeof abrirModalNuevo === 'function') {
                abrirModalNuevo();
            } else {
                document.getElementById('modalNuevoAlumno').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        @endif
    });
</script>

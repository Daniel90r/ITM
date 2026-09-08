{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- MODAL: NUEVO MAESTRO --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div id="modalNuevoMaestro" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:520px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px; max-height:90vh; overflow-y:auto;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Nuevo Maestro</p>
            </div>
            <button type="button" onclick="cerrarModalMaestro()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:14px; color:#6b7280; display:flex; align-items:center; justify-content:center;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.maestros.store') }}" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
            @csrf

            {{-- Nombre y Apellido --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nombre <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="nombre" id="nombreNuevoMaestro" required placeholder="Ej: Carlos"
                        value="{{ old('nombre') }}"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('nombre', 'storeMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Apellido <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="apellido" id="apellidoNuevoMaestro" required placeholder="Ej: Mendoza"
                        value="{{ old('apellido') }}"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('apellido', 'storeMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Teléfono y DUI --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Teléfono <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="telefono" id="telefonoNuevoMaestro" required maxlength="9"
                        inputmode="numeric" placeholder="Ej: 7123-4567" value="{{ old('telefono') }}"
                        pattern="[267][0-9]{3}-[0-9]{4}" autocomplete="tel"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('telefono', 'storeMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">DUI <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="dui" id="duiNuevoMaestro" required maxlength="10"
                        inputmode="numeric" placeholder="Ej: 01234567-8" value="{{ old('dui') }}"
                        pattern="[0-9]{8}-[0-9]" autocomplete="off"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('dui', 'storeMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Teléfono y DUI --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Teléfono <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="telefono" id="telefonoNuevoMaestro" value="{{ old('telefono') }}" required placeholder="Ej: 7777-8888" maxlength="9"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">DUI <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="dui" id="duiNuevoMaestro" value="{{ old('dui') }}" required placeholder="Ej: 00000000-0" maxlength="10"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
            </div>

            {{-- Código --}}
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Código <span style="color:#9ca3af; font-weight:400;">(No editable)</span></label>
                <input type="text" id="codigoMaestro" placeholder="Se generará al guardar" readonly
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f3f4f6; color:#6b7280; outline:none; box-sizing:border-box; font-family:monospace;">
                <p style="font-size:11px; color:#9ca3af; margin-top:4px;">El código se generará automáticamente al guardar.</p>
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
                    <input type="email" name="email" id="emailMaestro" required maxlength="255" autocomplete="email"
                        value="{{ old('email') }}"
                        placeholder="Ej: docente@itm.edu.sv"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @error('email', 'storeMaestro')
                    <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Contraseña --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Contraseña <span style="color:#dc2626;">*</span></label>
                        <input type="password" class="campo-secreto" name="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                        @error('password', 'storeMaestro')
                        <span style="display:block; color:#dc2626; font-size:11px; margin-top:4px;">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Confirmar contraseña <span style="color:#dc2626;">*</span></label>
                        <input type="password" class="campo-secreto" name="password_confirmation" required autocomplete="new-password" placeholder="Repetir contraseña"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>
            </div>

            {{-- Botones --}}
            <div style="display:flex; gap:10px; padding-top:4px;">
                <button type="button" onclick="cerrarModalMaestro()"
                    style="padding:10px 16px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="button" onclick="limpiarFormularioMaestro()"
                    style="padding:10px 16px; background:#ffffff; border:1px solid #e5e7eb; border-radius:10px; font-size:13px; font-weight:600; color:#6b7280; cursor:pointer;"
                    onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#ffffff'">
                    Limpiar
                </button>
                <button type="submit"
                    style="flex:1; padding:10px; background:#111827; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#ffffff; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                    Guardar maestro
                </button>
            </div>
        </form>
    </div>
</div>

@if($errors->storeMaestro->isNotEmpty())
<script>
    window.addEventListener('load', function() {
        abrirModalMaestro();
    });
</script>
@endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const nombreInput   = document.getElementById('nombreNuevoMaestro');
            const apellidoInput = document.getElementById('apellidoNuevoMaestro');
            const codigoInput   = document.getElementById('codigoMaestro');

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
            async function actualizarCodigoMaestro() {
                clearTimeout(debounce);
                debounce = setTimeout(async () => {
                    const nom = nombreInput?.value || '';
                    const ape = apellidoInput?.value || '';
                    const ini = iniciales(nom, ape);

                    if (ini.length < 2) {
                        if (codigoInput) codigoInput.value = '';
                        return;
                    }

                    try {
                        const res = await fetch('{{ route("admin.maestros.siguienteCodigo") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ nombre: nom, apellido: ape })
                        });
                        const data = await res.json();
                        if (codigoInput) codigoInput.value = data.codigo;
                    } catch (e) {
                        console.error('Error al calcular código:', e);
                    }
                }, 300);
            }

            if (nombreInput) nombreInput.addEventListener('input', actualizarCodigoMaestro);
            if (apellidoInput) apellidoInput.addEventListener('input', actualizarCodigoMaestro);

            window.limpiarFormularioMaestro = function () {
                const form = document.querySelector('#modalNuevoMaestro form');
                if (form) form.reset();
                if (codigoInput) codigoInput.value = '';
            };
        });
    </script>

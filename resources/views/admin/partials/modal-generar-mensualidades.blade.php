{{-- Aqui se genera las mensualidades --}}
<div id="modalGenerar" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:480px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Generar Mensualidad</p>
            </div>
            <button onclick="cerrarModalGenerar()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; color:#6b7280;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.mensualidades.generar') }}" autocomplete="off" style="display:flex; flex-direction:column; gap:14px;">
            @csrf

            {{-- Selección de alcance: todos / curso / uno --}}
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:8px;">Generar para</label>
                <div style="display:flex; gap:8px;">
                    <label style="flex:1; cursor:pointer;" onclick="toggleAlumno('todos')">
                        <input type="radio" name="tipo" value="todos" id="radioTodos" checked style="display:none;">
                        <div id="btnTodos" class="btn-alcance"
                            style="text-align:center; padding:9px 4px; border:2px solid #111827; border-radius:8px; font-size:12px; font-weight:600; color:#111827; background:#f3f4f6;">
                            Todos
                        </div>
                    </label>
                    <label style="flex:1; cursor:pointer;" onclick="toggleAlumno('curso')">
                        <input type="radio" name="tipo" value="curso" id="radioCurso" style="display:none;">
                        <div id="btnCurso" class="btn-alcance"
                            style="text-align:center; padding:9px 4px; border:2px solid #e5e7eb; border-radius:8px; font-size:12px; font-weight:600; color:#6b7280; background:#ffffff;">
                            Todo un curso
                        </div>
                    </label>
                    <label style="flex:1; cursor:pointer;" onclick="toggleAlumno('uno')">
                        <input type="radio" name="tipo" value="uno" id="radioUno" style="display:none;">
                        <div id="btnUno" class="btn-alcance"
                            style="text-align:center; padding:9px 4px; border:2px solid #e5e7eb; border-radius:8px; font-size:12px; font-weight:600; color:#6b7280; background:#ffffff;">
                            Un alumno
                        </div>
                    </label>
                </div>
            </div>

            {{-- Curso completo (oculto por defecto) --}}
            <div id="selectCursoDiv" style="display:none;">
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                    Curso <span style="color:#dc2626;">*</span>
                </label>
                <select name="curso_id" id="selectCursoLote"
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                    onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    <option value="">— Seleccionar curso —</option>
                    @foreach($cursos as $curso)
                    <option value="{{ $curso->id }}">{{ $curso->nombre }} — {{ $curso->nivel }} ({{ $curso->inscripciones->count() }} alumnos)</option>
                    @endforeach
                </select>
            </div>

            {{-- Alumno específico (oculto por defecto) --}}
            <div id="selectAlumnoDiv" style="display:none;">

                {{-- Filtrar por curso --}}
                <div style="margin-bottom:10px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Filtrar por curso</label>
                    <select id="filtroCursoModal" onchange="filtrarAlumnosModal()"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                        <option value="">Todos los cursos</option>
                        @foreach($cursos as $curso)
                        <option value="{{ $curso->id }}">{{ $curso->nombre }} — {{ $curso->nivel }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Select alumno --}}
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                        Alumno <span style="color:#dc2626;">*</span>
                    </label>
                    <select name="alumno_id" id="selectAlumno"
                        style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                        onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                        <option value="">— Selecciona un curso primero —</option>
                        @foreach($cursos as $curso)
                            @foreach($curso->inscripciones as $ins)
                                @if($ins->alumno)
                                <option value="{{ $ins->alumno_id }}"
                                    data-curso="{{ $curso->id }}"
                                    style="display:none;">
                                    {{ $ins->alumno->nombre_completo }}
                                </option>
                                @endif
                            @endforeach
                        @endforeach
                    </select>
                    <p id="sinAlumnosMsg" style="display:none; font-size:11px; color:#9ca3af; margin:4px 0 0;">No hay alumnos en este curso.</p>
                </div>
            </div>

            {{-- Año --}}
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                    Ciclo escolar <span style="color:#dc2626;">*</span>
                </label>
                <select name="anio" required
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                    onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    @foreach($aniosDisponibles as $a)
                    <option value="{{ $a }}" {{ $a == date('Y') ? 'selected' : '' }}>
                        Ciclo {{ $a }} {{ $a == date('Y') ? '(actual)' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Mes --}}
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                    Mes <span style="color:#dc2626;">*</span>
                </label>
                <select name="mes" required
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; cursor:pointer;"
                    onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    <option value="">Seleccionar mes...</option>
                    @foreach($meses as $num => $nombre)
                    <option value="{{ $nombre }}" {{ now()->month == intval($num) ? 'selected' : '' }}>
                        {{ $nombre }} {{ now()->year }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Aviso --}}
            <div style="background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:10px 14px; font-size:12px; color:#92400e;">
                ⚠ Si ya existe mensualidad para el mes y ciclo seleccionados, no se duplicará.
            </div>

            {{-- Botones --}}
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="cerrarModalGenerar()"
                    style="flex:1; padding:10px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="submit"
                    style="flex:1; padding:10px; background:#111827; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#fff; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                    Generar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Alcance de generación con 3 opciones: todos / curso / uno.
    // Reemplaza el toggleAlumno de mensualidades.js (que solo
    // conocía 2 opciones) una vez que el módulo de Vite cargó.
    document.addEventListener('DOMContentLoaded', function () {

        function pintarBoton(id, activo) {
            const btn = document.getElementById(id);
            btn.style.borderColor = activo ? '#111827' : '#e5e7eb';
            btn.style.background  = activo ? '#f3f4f6' : '#ffffff';
            btn.style.color       = activo ? '#111827' : '#6b7280';
        }

        window.toggleAlumno = function (tipo) {
            document.getElementById('radioTodos').checked = tipo === 'todos';
            document.getElementById('radioCurso').checked = tipo === 'curso';
            document.getElementById('radioUno').checked   = tipo === 'uno';

            document.getElementById('selectCursoDiv').style.display  = tipo === 'curso' ? 'block' : 'none';
            document.getElementById('selectAlumnoDiv').style.display = tipo === 'uno'   ? 'block' : 'none';

            document.getElementById('selectCursoLote').required = tipo === 'curso';
            document.getElementById('selectAlumno').required    = tipo === 'uno';

            pintarBoton('btnTodos', tipo === 'todos');
            pintarBoton('btnCurso', tipo === 'curso');
            pintarBoton('btnUno',   tipo === 'uno');
        };

        // Al cerrar el modal también se resetea el curso del lote
        const cerrarOriginal = window.cerrarModalGenerar;
        if (typeof cerrarOriginal === 'function') {
            window.cerrarModalGenerar = function () {
                cerrarOriginal();
                document.getElementById('selectCursoLote').value = '';
            };
        }
    });
</script>

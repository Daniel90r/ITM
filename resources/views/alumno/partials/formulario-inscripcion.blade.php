<div class="portal-form-card" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:24px;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:18px;">
        <div style="width:4px; height:16px; background:#f59e0b; border-radius:2px;"></div>
        <h3 style="font-size:15px; font-weight:600; color:#1f2937; margin:0;">Solicitud de Inscripción</h3>
    </div>

    {{-- Pasos --}}
    <div class="portal-pasos" style="display:flex; gap:0; margin-bottom:28px;">
        <div style="flex:1; text-align:center;">
            <div style="width:32px; height:32px; border-radius:50%; background:#111827; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; margin:0 auto 6px;">1</div>
            <p style="font-size:11px; color:#374151; font-weight:600; margin:0;">Llena el formulario</p>
            <p style="font-size:10px; color:#9ca3af; margin:0;">Datos digitales</p>
        </div>
        <div style="flex:0; display:flex; align-items:center; padding-bottom:20px;">
            <div style="width:40px; height:1px; background:#e5e7eb;"></div>
        </div>
        <div style="flex:1; text-align:center;">
            <div style="width:32px; height:32px; border-radius:50%; background:#d1d5db; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; margin:0 auto 6px;">2</div>
            <p style="font-size:11px; color:#374151; font-weight:600; margin:0;">Firma digital</p>
            <p style="font-size:10px; color:#9ca3af; margin:0;">{{ $alumno->es_mayor_de_edad ? 'Tu firma' : 'Tú y tu encargado' }}</p>
        </div>
        <div style="flex:0; display:flex; align-items:center; padding-bottom:20px;">
            <div style="width:40px; height:1px; background:#e5e7eb;"></div>
        </div>
        <div style="flex:1; text-align:center;">
            <div style="width:32px; height:32px; border-radius:50%; background:#d1d5db; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; margin:0 auto 6px;">3</div>
            <p style="font-size:11px; color:#374151; font-weight:600; margin:0;">Enviar solicitud</p>
            <p style="font-size:10px; color:#9ca3af; margin:0;">Admin revisa</p>
        </div>
    </div>

    <form method="POST" action="{{ route('alumno.inscripcion.enviar') }}" id="formInscripcion"
        data-encargado-bloqueado="{{ $estadoEncargado['error'] ? '1' : '0' }}"
        style="display:flex; flex-direction:column; gap:20px;">
        @csrf

        {{-- SECCIÓN 1: Datos del alumno (pre-llenados) --}}
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <p style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin:0 0 16px;">Datos del Alumno</p>
            <div class="portal-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:4px;">Nombre completo</label>
                    <div style="padding:9px 12px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827;">
                        {{ $alumno->nombre_completo }}
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:4px;">Código / Matrícula</label>
                    <div style="padding:9px 12px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827; font-family:monospace;">
                        {{ $alumno->codigo }}
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:4px;">Fecha de nacimiento</label>
                    <div style="padding:9px 12px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827;">
                        {{ $alumno->fecha_nacimiento ? \Carbon\Carbon::parse($alumno->fecha_nacimiento)->isoFormat('D [de] MMMM [de] YYYY') : '—' }}
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:4px;">Sexo</label>
                    <div style="padding:9px 12px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827;">
                        {{ $alumno->genero === 'M' ? 'Masculino' : ($alumno->genero === 'F' ? 'Femenino' : '—') }}
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:4px;">Teléfono</label>
                    <div style="padding:9px 12px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827;">
                        {{ $alumno->telefono ?? '—' }}
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:4px;">Dirección</label>
                    <div style="padding:9px 12px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827;">
                        {{ $alumno->direccion ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        @if($alumno->es_mayor_de_edad)


<div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
    <p style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin:0 0 16px;">
        Documento de Identidad
    </p>


    <div>
        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
            DUI del alumno <span style="color:#dc2626;">*</span>
        </label>


        <input
            type="text"
            name="alumno_dui"
            value="{{ old('alumno_dui', $alumno->dui) }}"
            required
            placeholder="Ej: 00000000-0"
            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#ffffff; color:#111827; outline:none; box-sizing:border-box;">
    </div>
</div>


@else
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <p style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin:0 0 16px;">Datos oficiales del Encargado</p>
            @if($estadoEncargado['error'])
                <div role="alert" style="color:#dc2626; margin-bottom:14px;">{{ $estadoEncargado['error'] }}</div>
            @endif
            @if($estadoEncargado['datos'])
                <div class="portal-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    @foreach(['nombre' => 'Nombre', 'apellido' => 'Apellido', 'dui' => 'DUI', 'telefono' => 'Teléfono (opcional)', 'parentesco' => 'Parentesco'] as $campo => $etiqueta)
                        <div>
                            <div style="display:block; font-size:11px; color:#6b7280; margin-bottom:4px;">{{ $etiqueta }}</div>
                            <div style="padding:9px 12px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827;">{{ $estadoEncargado['datos']['encargado_'.$campo] ?: '—' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

@endif

        {{-- SECCIÓN 3: Selección de curso --}}
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <p style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin:0 0 16px;">Selección de Sección</p>
            <div class="portal-cursos" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px;">
                @forelse($cursosDisponibles as $curso)
                @php $lleno = $curso->esta_lleno; @endphp
                <label style="cursor:{{ $lleno ? 'not-allowed' : 'pointer' }};">
                    <input type="radio" name="curso_id" value="{{ $curso->id }}" class="curso-radio portal-radio" {{ $lleno ? 'disabled' : '' }} required>
                    <div class="curso-card" style="background:{{ $lleno ? '#f9fafb' : '#ffffff' }}; border:2px solid #e5e7eb; border-radius:10px; padding:14px; text-align:center; transition:all .15s; {{ $lleno ? 'opacity:0.6;' : '' }}">
                        <p style="font-size:18px; font-weight:700; color:#111827; margin:0 0 2px;">{{ $curso->seccion }}</p>
                        <p style="font-size:12px; font-weight:500; color:#374151; margin:0 0 2px;">{{ $curso->nombre }}</p>
                        <p style="font-size:11px; color:#6b7280; margin:0;">{{ $curso->nivel }}</p>
                        <p style="font-size:11px; margin:4px 0 0; {{ $lleno ? 'color:#dc2626;' : 'color:#16a34a;' }}">
                            @if($lleno)
                                Cupo lleno
                            @else
                                {{ $curso->cupos_disponibles }} cupo(s) disponible(s) de 20
                            @endif
                        </p>
                    </div>
                </label>
                @empty
                <div style="grid-column:1 / -1; background:#f9fafb; border:1px dashed #d1d5db; border-radius:10px; padding:24px; text-align:center;">
                    <p style="font-size:13px; font-weight:600; color:#374151; margin:0 0 4px;">No hay cursos disponibles para inscripción.</p>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Por el momento no hay cursos en estado Próximo. Vuelve a consultar más adelante.</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- SECCIÓN 3.5: Opción de Inglés --}}
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                <input type="checkbox" name="tomara_ingles" value="1"
                    style="width:18px; height:18px; margin-top:2px; accent-color:#f59e0b; cursor:pointer;">
                <div>
                    <p style="font-size:13px; font-weight:600; color:#374151; margin:0;">Deseo tomar el curso de Inglés</p>
                    <p style="font-size:11px; color:#6b7280; margin:2px 0 0;">Se agregará la materia de Inglés a tu inscripción al aprobarse tu solicitud.</p>
                </div>
            </label>
        </div>

        {{-- SECCIÓN 4: Firmas digitales --}}
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <p style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin:0 0 6px;">Firmas Digitales</p>
            <p style="font-size:12px; color:#6b7280; margin:0 0 16px;">
                {{ $alumno->es_mayor_de_edad
                    ? 'Dibuja tu firma usando el mouse o el dedo en pantalla táctil.'
                    : 'Dibuja tu firma y la firma del encargado usando el mouse o el dedo en pantalla táctil.' }}
            </p>

            <div class="portal-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

                {{-- Firma del alumno --}}
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:8px;">Firma del Alumno <span style="color:#dc2626;">*</span></label>
                    <canvas aria-label="Dibujar firma del alumno" id="firmaAlumno" width="300" height="120"
                        style="width:100%; height:120px; border:2px solid #e5e7eb; border-radius:10px; background:#ffffff; cursor:crosshair; touch-action:none;">
                    </canvas>
                    <input type="hidden" name="firma_alumno" id="firmaAlumnoData">
                    <div style="display:flex; justify-content:flex-end; margin-top:6px;">
                        <button type="button" onclick="limpiarFirma('firmaAlumno', 'firmaAlumnoData')"
                            style="background:none; border:1px solid #e5e7eb; color:#6b7280; font-size:11px; padding:4px 10px; border-radius:6px; cursor:pointer;">
                            Limpiar
                        </button>
                    </div>
                </div>

                {{-- Firma del encargado --}}
                @if(!$alumno->es_mayor_de_edad)
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:8px;">Firma del Encargado <span style="color:#dc2626;">*</span></label>
                    <canvas aria-label="Dibujar firma del encargado" id="firmaEncargado" width="300" height="120"
                        style="width:100%; height:120px; border:2px solid #e5e7eb; border-radius:10px; background:#ffffff; cursor:crosshair; touch-action:none;">
                    </canvas>
                    <input type="hidden" name="firma_encargado" id="firmaEncargadoData">
                    <div style="display:flex; justify-content:flex-end; margin-top:6px;">
                        <button type="button" onclick="limpiarFirma('firmaEncargado', 'firmaEncargadoData')"
                            style="background:none; border:1px solid #e5e7eb; color:#6b7280; font-size:11px; padding:4px 10px; border-radius:6px; cursor:pointer;">
                            Limpiar
                        </button>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Botón enviar --}}
        <div>
            <button type="button" onclick="enviarSolicitud()" @disabled($estadoEncargado['error'] !== null)
                style="padding:12px 28px; background:#f59e0b; border:none; border-radius:10px; color:#fff; font-size:14px; font-weight:700; cursor:pointer;"
                onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'">
                Enviar solicitud de inscripción
            </button>
        </div>
    </form>
</div>

@push('scripts')
@vite('resources/js/alumno/inscripcion.js')
@endpush

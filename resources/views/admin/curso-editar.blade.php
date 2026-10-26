<x-app-layout>
@push('styles')
<style>
    .curso-horarios-edicion{position:fixed;inset:0;display:flex;height:100vh;height:100dvh;min-height:0;overflow:hidden;background:#f8fafc}
    .curso-horarios-edicion>.ce-panel{flex:1;display:flex;flex-direction:column;min-width:0;min-height:0;overflow:hidden;background:#f8fafc}
    .curso-horarios-edicion .ce-form-scroll{flex:1;min-width:0;min-height:0;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;padding:24px;box-sizing:border-box;background:#f8fafc}
    @media screen and (min-width:1024px){
        .curso-horarios-edicion>[data-staff-sidebar]{height:100vh;height:100dvh;min-height:0;box-sizing:border-box;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain}
        .curso-horarios-edicion>[data-staff-sidebar]>*{flex-shrink:0}
    }
    @media screen and (max-width:1023px){
        .curso-horarios-edicion .ce-form-scroll{flex:0 0 auto;overflow:visible;padding:16px}
    }
</style>
@endpush
<div class="curso-horarios-edicion">

    @include('components.admin-sidebar', ['active' => 'cursos'])

    <div class="ce-panel">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; flex-wrap:wrap; align-items:center; gap:12px; flex-shrink:0;">
                        <x-boton-regreso :href="route('admin.cursos')">Volver a Cursos</x-boton-regreso>
            <div>
                <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Editar Curso</h2>
                <p style="font-size:12px; color:#6b7280; margin:0;">{{ $curso->nombre }}</p>
            </div>
        </header>

        <div class="ce-form-scroll">
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:28px; max-width:560px;">

                @if($errors->any())
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 16px; margin-bottom:20px;">
                    @foreach($errors->all() as $error)
                    <p style="font-size:12px; color:#dc2626; margin:0;">{{ $error }}</p>
                    @endforeach
                </div>
                @endif

                <form method="POST" action="{{ route('admin.cursos.update', $curso->id) }}" autocomplete="off" style="display:flex; flex-direction:column; gap:18px;">
                    @csrf
                    @method('PUT')

                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nombre</label>
                        <input type="text" name="nombre" value="{{ old('nombre', $curso->nombre) }}" required maxlength="100"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>

                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Nivel / Turno</label>
                        <select name="nivel" style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                            <option value="Matutino" {{ $curso->nivel === 'Matutino' ? 'selected' : '' }}>Matutino</option>
                            <option value="Vespertino" {{ $curso->nivel === 'Vespertino' ? 'selected' : '' }}>Vespertino</option>
                        </select>
                    </div>

                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Sección</label>
                        <input type="text" name="seccion" value="{{ old('seccion', $curso->seccion) }}" required maxlength="20"
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>

                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Año lectivo</label>
                        <input type="number" name="anio_lectivo" value="{{ old('anio_lectivo', $curso->anio_lectivo) }}" required
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                        @php
                            $inicioBloqueado = in_array($curso->estado_curso, [\App\Models\Curso::ESTADO_ACTIVO, \App\Models\Curso::ESTADO_FINALIZADO], true);
                        @endphp
                        <div>
                            <label for="curso-fecha-inicio" style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Fecha de inicio <span style="color:red">*</span></label>
                            <input id="curso-fecha-inicio" type="date" name="fecha_inicio" value="{{ $inicioBloqueado ? $curso->fecha_inicio?->format('Y-m-d') : old('fecha_inicio', $curso->fecha_inicio?->format('Y-m-d')) }}" required @readonly($inicioBloqueado) @if($inicioBloqueado) aria-describedby="curso-inicio-ayuda" @endif
                                style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                                onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                            @if($inicioBloqueado)
                                <p id="curso-inicio-ayuda" style="font-size:12px; color:#64748b; margin:6px 0 0;">{{ $curso->estado_curso === \App\Models\Curso::ESTADO_FINALIZADO ? 'La fecha de inicio no puede modificarse porque el curso ya está finalizado' : 'La fecha de inicio no puede modificarse porque el curso ya está activo' }}</p>
                            @endif
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Fecha de finalización <span style="color:red">*</span></label>
                            <input type="date" name="fecha_fin" value="{{ old('fecha_fin', $curso->fecha_fin?->format('Y-m-d')) }}" required
                                style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                                onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                     </div>

                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                            Estado
                            @if($curso->estado_curso === 'FINALIZADO')
                                <span style="color:#6b7280; font-weight:400;">(estado terminal, no se puede modificar)</span>
                            @else
                                <span style="color:red">*</span>
                            @endif
                        </label>
                        @php $esFinalizado = $curso->estado_curso === 'FINALIZADO'; @endphp
                        <select name="estado" @if($esFinalizado) disabled @endif
                            style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; {{ $esFinalizado ? 'opacity:0.6; cursor:not-allowed;' : '' }}"
                            onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                            @if($esFinalizado)
                                <option value="FINALIZADO" selected>Finalizado</option>
                            @else
                                <option value="PROXIMO" {{ old('estado', $curso->estado_editable) === 'PROXIMO' ? 'selected' : '' }}>Próximo</option>
                                <option value="ACTIVO" {{ old('estado', $curso->estado_editable) === 'ACTIVO' ? 'selected' : '' }}>Activo</option>
                            @endif
                        </select>
                        @if($esFinalizado)
                            <p style="font-size:11px; color:#6b7280; margin:6px 0 0;">Este curso ya fue finalizado. «Finalizado» es un estado terminal y solo se revierte desde el flujo de cursos, no desde este formulario.</p>
                        @else
                            <p style="font-size:11px; color:#6b7280; margin:6px 0 0;">«Finalizado» no se selecciona aquí: se aplica con el botón <strong>Finalizar curso</strong>.</p>
                        @endif
                    </div>

                    @include('admin.partials.horario-semanal', ['curso' => $curso])
                     <div style="display:flex; gap:10px; padding-top:4px;">
            <a href="{{ route('admin.cursos') }}"
                           style="flex:1; padding:10px; border-radius:10px; background:#f3f4f6; color:#374151; font-size:13px; font-weight:600; border:none; cursor:pointer; text-align:center; text-decoration:none;"
                           onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                            Cancelar
                        </a>
                        <button type="submit"
                            style="flex:1; padding:10px; border-radius:10px; background:#111827; color:#fff; font-size:13px; font-weight:600; border:none; cursor:pointer;"
                            onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@push('scripts')
@vite('resources/js/admin/cursos.js')
@endpush
</x-app-layout>

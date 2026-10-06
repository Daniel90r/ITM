<x-app-layout>
<style>
    .boleta-layout {
        display: flex;
        height: 100vh;
        overflow: hidden;
    }

    .boleta-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #f8fafc;
    }

    .boleta-header {
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        padding: 14px 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .boleta-title-block {
        min-width: 0;
    }

    .boleta-title {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
        color: #111827;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .boleta-subtitle {
        font-size: 12px;
        color: #6b7280;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .boleta-content {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
    }

    .boleta-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .boleta-info-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .boleta-student {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .boleta-avatar {
        flex-shrink: 0;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #111827;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 16px;
        font-weight: 700;
    }

    .boleta-student-name {
        font-size: 16px;
        font-weight: 700;
        color: #111827;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .boleta-student-meta {
        font-size: 12px;
        color: #6b7280;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .boleta-average {
        text-align: center;
        padding: 12px 24px;
        background: #f9fafb;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        flex-shrink: 0;
    }

    .boleta-average-label {
        font-size: 11px;
        color: #6b7280;
        margin: 0 0 4px;
        text-transform: uppercase;
        font-weight: 600;
    }

    .boleta-average-value {
        font-size: 28px;
        font-weight: 700;
        margin: 0;
    }

    .boleta-table-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        overflow: hidden;
    }

    .boleta-section-header {
        padding: 16px 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .boleta-section-title {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        margin: 0;
    }

    .boleta-table {
        width: 100%;
        border-collapse: collapse;
    }

    .boleta-table thead th {
        padding: 11px 24px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }

    .boleta-table tbody td,
    .boleta-table tfoot td {
        padding: 13px 24px;
        font-size: 13px;
        color: #374151;
    }

    .boleta-table tbody tr {
        border-top: 1px solid #f1f5f9;
    }

    .boleta-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }

    .boleta-table tfoot tr {
        background: #111827;
        border-top: 2px solid #111827;
    }

    .boleta-table tfoot td {
        color: #ffffff;
    }

    .boleta-grade-pill {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
    }

    .boleta-letter-pill {
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-block;
        min-width: 32px;
        text-align: center;
    }

    .boleta-grade-card {
        display: none;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 12px;
    }

    .boleta-grade-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        padding: 14px;
        border-bottom: 1px solid #f1f5f9;
    }

    .boleta-grade-card-title {
        font-size: 14px;
        font-weight: 700;
        color: #111827;
        margin: 0 0 4px;
    }

    .boleta-grade-card-teacher {
        font-size: 12px;
        color: #6b7280;
        margin: 0;
    }

    .boleta-grade-card-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        padding: 14px;
    }

    .boleta-grade-card-item {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px;
    }

    .boleta-grade-card-label {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        font-weight: 600;
        margin: 0 0 4px;
    }

    .boleta-grade-card-value {
        font-size: 16px;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }

    .boleta-empty {
        padding: 40px;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
    }

    @media (max-width: 768px) {
        .boleta-layout {
            display: block;
            height: 100vh;
            overflow: auto;
        }

        .boleta-main {
            height: calc(100vh - 76px);
        }

        .boleta-header {
            padding: 12px 14px;
            gap: 8px;
        }

        .boleta-title {
            max-width: calc(100vw - 150px);
            font-size: 14px;
        }

        .boleta-subtitle {
            max-width: calc(100vw - 150px);
            font-size: 11px;
        }

        .boleta-content {
            padding: 14px;
        }

        .boleta-card {
            padding: 14px;
            margin-bottom: 14px;
        }

        .boleta-info-card {
            display: block;
        }

        .boleta-student {
            gap: 10px;
        }

        .boleta-avatar {
            width: 44px;
            height: 44px;
            font-size: 14px;
        }

        .boleta-student-name {
            font-size: 14px;
        }

        .boleta-student-meta {
            font-size: 11px;
        }

        .boleta-average {
            margin-top: 14px;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .boleta-average-label {
            margin: 0;
            font-size: 10px;
        }

        .boleta-average-value {
            margin: 0;
            font-size: 24px;
        }

        .boleta-section-header {
            padding: 14px;
        }

        .boleta-section-title {
            font-size: 13px;
        }

        .boleta-table {
            display: none;
        }

        .boleta-grade-card {
            display: block;
        }

        .boleta-grade-card-grid {
            grid-template-columns: 1fr;
            padding: 12px;
        }

        .boleta-empty {
            padding: 28px 12px;
        }
    }

    @media (max-width: 380px) {
        .boleta-header .boleta-pdf-link {
            padding: 7px 10px;
            font-size: 11px;
        }

        .boleta-title {
            max-width: calc(100vw - 125px);
        }

        .boleta-subtitle {
            max-width: calc(100vw - 125px);
        }
    }

    .boleta-selector {
        padding: 24px;
    }

    .boleta-selector-title {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .boleta-selector-input {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 14px;
        min-width: 180px;
    }

    .boleta-selector-btn {
        background: #111827;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
</style>

<div class="boleta-layout">
    @include('components.admin-sidebar', ['active' => 'boletas'])

    <div class="boleta-main">
        <header class="boleta-header">
            <a href="{{ route('admin.boletas') }}"
               style="width:32px; height:32px; border-radius:8px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; text-decoration:none; color:#6b7280; flex-shrink:0;"
               onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <div class="boleta-title-block">
                <h2 class="boleta-title">Boleta — {{ $alumno->nombre_completo }}</h2>
                 <p class="boleta-subtitle">{{ $alumno->codigo }} · Ciclo {{ $curso?->anio_lectivo ?? '—' }}</p>
            </div>
            @if($curso)
            <div style="margin-left:auto;">
                <a href="{{ route('admin.boletas.pdf', [$alumno->id, 'curso_id' => $curso->id]) }}"
                   class="boleta-pdf-link"
                   style="display:inline-flex; align-items:center; gap:6px; background:#111827; border:none; padding:8px 16px; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none;"
                   onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Descargar PDF
                </a>
            </div>
            @endif
        </header>

        <div class="boleta-content">
            @if($curso)

            <div class="boleta-card boleta-info-card">
                <div class="boleta-student">
                    <div class="boleta-avatar">
                        {{ strtoupper(substr($alumno->nombre,0,1).substr($alumno->apellido,0,1)) }}
                    </div>
                    <div style="min-width:0;">
                        <p class="boleta-student-name">{{ $alumno->nombre_completo }}</p>
                        <p class="boleta-student-meta">Código: {{ $alumno->codigo }} · {{ $curso?->nombre ?? '—' }} · {{ $curso?->nivel ?? '' }}</p>
                    </div>
                </div>
                <div class="boleta-average">
                    <p class="boleta-average-label">Promedio General</p>
                    @php $pg = $promedio_general; @endphp
                    <p class="boleta-average-value" style="color:{{ $pg >= 9 ? '#16a34a' : ($pg >= 8 ? '#0284c7' : '#d97706') }};">
                        {{ $pg ? round($pg, 1) : '—' }}
                    </p>
                </div>
            </div>

            <div class="boleta-table-card">
                <div class="boleta-section-header">
                    <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                    <p class="boleta-section-title">Detalle de Notas</p>
                </div>

                <table class="boleta-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Materia</th>
                            <th style="text-align:left;">Maestro actual</th>
                            <th style="text-align:left;">Registrado por</th>
                            <th style="text-align:center;">Laboratorio</th>
                            <th style="text-align:center;">Teórico</th>
                            <th style="text-align:center;">Práctico</th>
                            <th style="text-align:center;">Promedio</th>
                            <th style="text-align:center;">Concepto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notas as $nota)
                        @php
                            $p = $nota->promedio;
                            if (!$p)        { $letra = '—';  $color = '#9ca3af'; }
                            elseif($p >= 9) { $letra = 'E';  $color = '#16a34a'; }
                            elseif($p >= 8) { $letra = 'MB'; $color = '#0284c7'; }
                            else            { $letra = 'B';  $color = '#d97706'; }
                        @endphp
                        <tr>
                            <td style="padding:13px 24px; font-size:13px; font-weight:500; color:#111827;">
                                {{ $nota->detalleCurso->materia->nombre ?? '—' }}
                            </td>
                            <td style="padding:13px 24px; font-size:13px; color:#6b7280;">
                                {{ $nota->detalleCurso->maestro->nombre ?? '' }} {{ $nota->detalleCurso->maestro->apellido ?? '' }}
                            </td>
                            <td style="padding:13px 24px; font-size:13px; color:#6b7280;">
                                {{ $nota->maestro->nombre ?? '' }} {{ $nota->maestro->apellido ?? '' }}
                            </td>
                            <td style="padding:13px 24px; text-align:center; font-size:13px; color:#374151;">
                                {{ $nota->laboratorio ?? '—' }}
                            </td>
                            <td style="padding:13px 24px; text-align:center; font-size:13px; color:#374151;">
                                {{ $nota->examen_teorico ?? '—' }}
                            </td>
                            <td style="padding:13px 24px; text-align:center; font-size:13px; color:#374151;">
                                {{ $nota->practica ?? '—' }}
                            </td>
                            <td style="padding:13px 24px; text-align:center;">
                                <span class="boleta-grade-pill" style="background:{{ $p ? ($p >= 9 ? '#16a34a' : ($p >= 8 ? '#0284c7' : '#d97706')) : '#9ca3af' }};">
                                    {{ $p ?? '—' }}
                                </span>
                            </td>
                            <td style="padding:13px 24px; text-align:center;">
                                <span class="boleta-letter-pill" style="background:{{ $color }};">
                                    {{ $letra }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="boleta-empty">
                                Sin notas registradas.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>

                    @if($notas->count() > 0)
                    <tfoot>
                         <tr>
                             <td colspan="6" style="padding:12px 24px; font-size:13px; font-weight:700; color:#ffffff; text-align:left;">
                                 Promedio General
                             </td>
                             <td style="padding:12px 24px; text-align:center;">
                                <span class="boleta-grade-pill" style="color:#111827; background:#ffffff;">
                                    {{ $promedio_general ? round($promedio_general) : '—' }}
                                </span>
                            </td>
                            <td style="padding:12px 24px; text-align:center;">
                                @php
                                    $pg = $promedio_general;
                                    if (!$pg)        { $lPG = '—'; }
                                    elseif($pg >= 9) { $lPG = 'E'; }
                                    elseif($pg >= 8) { $lPG = 'MB'; }
                                    else             { $lPG = 'B'; }
                                @endphp
                                <span style="background:#ffffff; color:#111827; font-size:12px; font-weight:700; padding:4px 10px; border-radius:6px; display:inline-block;">
                                    {{ $lPG }}
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>

                 @forelse($notas as $nota)
                 @php
                     $p = $nota->promedio;
                     if (!$p)        { $letra = '—';  $color = '#9ca3af'; }
                     elseif($p >= 9) { $letra = 'E';  $color = '#16a34a'; }
                     elseif($p >= 8) { $letra = 'MB'; $color = '#0284c7'; }
                     else            { $letra = 'B';  $color = '#d97706'; }
                 @endphp
                 <div class="boleta-grade-card">
                     <div class="boleta-grade-card-header">
                         <div style="min-width:0;">
                             <p class="boleta-grade-card-title">{{ $nota->detalleCurso->materia->nombre ?? '—' }}</p>
                             <p class="boleta-grade-card-teacher">Actual: {{ $nota->detalleCurso->maestro->nombre ?? '' }} {{ $nota->detalleCurso->maestro->apellido ?? '' }}</p>
                             <p class="boleta-grade-card-teacher">Registrado por: {{ $nota->maestro->nombre ?? '' }} {{ $nota->maestro->apellido ?? '' }}</p>
                         </div>
                         <span class="boleta-letter-pill" style="background:{{ $color }};">
                             {{ $letra }}
                         </span>
                     </div>
                    <div class="boleta-grade-card-grid">
                        <div class="boleta-grade-card-item">
                            <p class="boleta-grade-card-label">Laboratorio</p>
                            <p class="boleta-grade-card-value">{{ $nota->laboratorio ?? '—' }}</p>
                        </div>
                        <div class="boleta-grade-card-item">
                            <p class="boleta-grade-card-label">Teórico</p>
                            <p class="boleta-grade-card-value">{{ $nota->examen_teorico ?? '—' }}</p>
                        </div>
                        <div class="boleta-grade-card-item">
                            <p class="boleta-grade-card-label">Práctico</p>
                            <p class="boleta-grade-card-value">{{ $nota->practica ?? '—' }}</p>
                        </div>
                        <div class="boleta-grade-card-item">
                            <p class="boleta-grade-card-label">Promedio</p>
                            <p class="boleta-grade-card-value">
                                <span class="boleta-grade-pill" style="width:30px; height:30px; font-size:12px; background:{{ $p ? ($p >= 9 ? '#16a34a' : ($p >= 8 ? '#0284c7' : '#d97706')) : '#9ca3af' }};">
                                    {{ $p ?? '—' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
                @empty
                <div class="boleta-empty">
                    Sin notas registradas.
                </div>
                 @endforelse
                 @else
                 @if($cursosAprobados && $cursosAprobados->isNotEmpty())
                 <div class="boleta-selector">
                     <p class="boleta-selector-title">Seleccionar curso / ciclo</p>
                     <form method="GET" action="{{ route('admin.boletas.show', ['alumno_id' => $alumno->id]) }}" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                         @if($cursosAprobados->count() > 1)
                         <select name="curso_id" class="boleta-selector-input">
                             <option value="">-- Curso --</option>
                             @foreach($cursosAprobados as $c)
                             <option value="{{ $c->id }}" {{ (request('curso_id') == $c->id) ? 'selected' : '' }}>{{ $c->nombre }} ({{ $c->anio_lectivo ?? '—' }})</option>
                             @endforeach
                         </select>
                         @endif
                         <button type="submit" class="boleta-selector-btn">Aplicar</button>
                     </form>
                 </div>
                 @else
                 <p class="boleta-empty">El alumno no tiene inscripciones aprobadas.</p>
                 @endif
                 @endif
             </div>
         </div>
    </div>
</div>
</x-app-layout>

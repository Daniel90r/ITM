<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="{{ asset('images/logo_itm.jpg') }}">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; font-size:11px; color:#1a1a2e; padding:24px 30px; }

        .header-table { width:100%; border-collapse:collapse; margin-bottom:10px; }
        .logo-circle {
            width:58px; height:58px; border-radius:50%;
            border:2px solid #1a3a6b;
            display:inline-block;
            font-size:9px; color:#1a3a6b; font-weight:bold;
            text-align:center; padding-top:14px; line-height:1.4;
        }
        .inst-title { font-size:14px; font-weight:bold; color:#1a3a6b; letter-spacing:1px; text-align:center; }
        .inst-sub   { font-size:11px; font-weight:bold; color:#1a3a6b; text-align:center; }
        .divider { border:none; border-top:2px solid #1a3a6b; margin:10px 0; }
        .titulo-boleta {
            text-align:center; font-size:22px; font-weight:bold;
            color:#1a3a6b; letter-spacing:4px;
            border:2px solid #1a3a6b; padding:8px 0; margin-bottom:14px;
        }
        .intro { font-size:11px; margin-bottom:14px; line-height:1.7; }

        /* ── TABLA ÚNICA ── */
        .tabla-principal { width:100%; border-collapse:collapse; margin-bottom:14px; }

        .tabla-principal th {
            background:#1a3a6b; color:#fff;
            font-size:9px; font-weight:bold;
            padding:6px 5px; text-align:center;
            text-transform:uppercase; letter-spacing:.3px;
            border:1px solid #1a3a6b;
        }
        .tabla-principal th.th-left { text-align:left; }

        .tabla-principal td {
            font-size:10px;
            border:1px solid #b0b8cc;
            text-align:center;
            height:22px;
            padding:0 5px;
        }
        .tabla-principal td.td-left {
            text-align:left; font-weight:bold; padding-left:6px;
        }
        .tabla-principal tr:nth-child(even) td.td-materia,
        .tabla-principal tr:nth-child(even) td.td-prom,
        .tabla-principal tr:nth-child(even) td.td-conc {
            background:#eef2ff;
        }

        .td-final {
            background:#1a3a6b !important;
            color:#fff; font-weight:bold; font-size:10px;
            border:1px solid #1a3a6b;
        }
        .td-resultado {
            background:#1a3a6b !important;
            color:#fff; font-weight:bold; font-size:10px;
            text-align:center;
            border:1px solid #1a3a6b;
        }
        .td-conducta {
            text-align:left; font-size:10px; font-weight:500;
            padding-left:6px; vertical-align:middle;
            border:1px solid #b0b8cc;
        }
        .td-concepto-conducta {
            text-align:center; font-size:10px; font-weight:bold;
            color:#1a3a6b; vertical-align:middle;
            border:1px solid #b0b8cc;
        }

        .texto-legal { font-size:10.5px; line-height:1.7; margin-bottom:20px; text-align:justify; }
        .firmas-table { width:100%; border-collapse:collapse; margin-top:30px; }
        .firma-linea { border-top:1px solid #1a1a2e; padding-top:6px; margin-top:40px; }
        .firma-nombre { font-weight:bold; font-size:11px; }
        .firma-cargo  { font-size:10px; color:#555; }
        .sello {
            width:72px; height:72px; border-radius:50%;
            border:2px solid #1a3a6b; display:inline-block;
            font-size:8px; color:#1a3a6b; font-weight:bold;
            text-align:center; padding-top:12px; line-height:1.4;
        }
    </style>
</head>
<body>

{{-- ENCABEZADO --}}
<table class="header-table">
    <tr>
        <td style="width:12%; text-align:center;">
            <div class="logo-circle">ITM</div>
        </td>
        <td style="width:76%; text-align:center;">
            <div class="inst-title">ITM</div>
            <div class="inst-sub">INSTITUTO DE COMPUTACIÓN DE AGUILARES</div>
        </td>
        <td style="width:12%; text-align:center;">
            <div class="logo-circle" style="font-size:7px; padding-top:10px;">REPÚBLICA<br>DE EL<br>SALVADOR</div>
        </td>
    </tr>
</table>

<hr class="divider">
<div class="titulo-boleta">BOLETA DE NOTAS</div>

<p class="intro">
    El <strong>Instituto de Computación I. T. M</strong> hace constar que el/a alumno/a:<br>
    <strong>{{ strtoupper($alumno->nombre . ' ' . $alumno->apellido) }}</strong>
    ha cursado una carrera técnica en informática obteniendo las siguientes calificaciones:
</p>

@php
    $notas = $notas ?? $alumno->notas;
    $materiasBase = [
        'Microsoft Windows','Microsoft Word','Microsoft Excel',
        'Internet','Microsoft PowerPoint','Microsoft Publisher',
        'Microsoft Access','CorelDraw','Photoshop',
        'HTML','Macromedia Dreamweaver','Mantenimiento de PC','Redes',
    ];

    // Fase 1 — Colisión por nombre: la clave es detalle_curso_id (identificador
    // estructural estable), NUNCA el nombre de la materia. Así, materias con el
    // mismo nombre en distintos cursos no se sobreescriben.
    $notasPorDetalle = [];
    foreach ($notas as $nota) {
        if ($nota->detalle_curso_id !== null) {
            $notasPorDetalle[$nota->detalle_curso_id] = $nota;
        }
    }

    // Una fila por cada nota (materia) real; si no hay notas, fallback visual
    // con las materias-base (sin conducta ni promedio registrados).
    if ($notas->count() > 0) {
        $filas = $notas->map->detalle_curso_id->filter()->unique()->values();
    } else {
        $filas = collect($materiasBase);
    }

    $totalFilas = $filas->count();

    // Los 4 aspectos de conducta se conservan visualmente (distribuidos uniformemente).
    // NOTA: el sistema almacena UNA conducta por materia (B/MB/E), no un valor por
    // aspecto. Por eso la conducta se muestra por fila de materia.
    $conductas = [
        'Responsabilidad',
        'Relaciones Personales',
        'Iniciativa y Confianza en sí mismo',
        'Hábitos de estudio y trabajo',
    ];
    $numConductas = count($conductas);

    $filasPorConducta = $totalFilas > 0 ? floor($totalFilas / $numConductas) : 0;
    $resto = $totalFilas > 0 ? $totalFilas % $numConductas : 0;

    $conducta_map = [];
    $filaActual = 0;
    foreach ($conductas as $ic => $cond) {
        $span = $filasPorConducta + ($ic < $resto ? 1 : 0);
        if ($span > 0) {
            $conducta_map[$filaActual] = ['nombre' => $cond, 'span' => $span];
            $filaActual += $span;
        }
    }

    $concepto = $concepto ?? function ($p) {
        if (! $p) return '';
        if ($p >= 9) return 'E';
        if ($p >= 8) return 'MB';
        if ($p >= 7) return 'B';
        if ($p >= 6) return 'R';
        return 'D';
    };
@endphp

{{-- TABLA ÚNICA --}}
<table class="tabla-principal">
    <thead>
        <tr>
            <th class="th-left" style="width:32%;">Programa</th>
            <th style="width:16%;">Promedio de<br>Calificación</th>
            <th style="width:10%;">Conceptos</th>
            <th style="width:28%;">Aspectos de Conducta</th>
            <th style="width:14%;">Conducta registrada</th>
        </tr>
    </thead>
    <tbody>
        @foreach($filas as $i => $fila)
        @php
            $esNota = is_int($fila);
            $nota = $esNota ? ($notasPorDetalle[$fila] ?? null) : null;
            $nombreMateria = $esNota
                ? ($nota?->detalleCurso?->materia?->nombre ?? '—')
                : $fila;
            $prom = $nota?->promedio ?? null;
            $esPar = ($i % 2 === 0);
            $bg    = $esPar ? '#ffffff' : '#eef2ff';
        @endphp
        <tr>
            <td class="td-left td-materia" style="background:{{ $bg }};">{{ $nombreMateria }}</td>
            <td class="td-prom" style="background:{{ $bg }};">{{ $prom ?? '' }}</td>
            <td class="td-conc" style="font-weight:bold; color:#1a3a6b; background:{{ $bg }};">{{ $concepto($prom) }}</td>

            @if(isset($conducta_map[$i]))
            @php $cond = $conducta_map[$i]; @endphp
            <td class="td-conducta" rowspan="{{ $cond['span'] }}" style="background:{{ $bg }};">{{ $cond['nombre'] }}</td>
            @endif
            <td class="td-concepto-conducta" style="background:{{ $bg }};">{{ $nota->conducta ?? '—' }}</td>
        </tr>
        @endforeach

        {{-- Fila promedio final --}}
        <tr>
            <td class="td-left td-final">PROMEDIO FINAL</td>
            <td class="td-final" style="text-align:center;">
                {{ $promedio_general ? round($promedio_general) : '—' }}
            </td>
            <td class="td-final"></td>
            <td colspan="2" class="td-resultado">
                {{ ($promedio_general && $promedio_general >= 6) ? 'Aprobado' : 'Reprobado' }}
            </td>
        </tr>
    </tbody>
</table>

{{-- TEXTO LEGAL --}}
<p class="texto-legal">
    Por lo tanto, queda facultado/a para trabajar en los programas recibidos en el periodo de
    <strong>dos años</strong>, el cual se extiende la boleta de calificación en la ciudad de
    Aguilares, Departamento de San Salvador, a los
    <strong>{{ now()->isoFormat('D') }} días del mes de {{ now()->isoFormat('MMMM YYYY') }}</strong>.
</p>

{{-- FIRMAS --}}
<table class="firmas-table">
    <tr>
        <td style="width:33%; text-align:center;">
            <div class="firma-linea">
                <div class="firma-nombre">Santos David Rodas</div>
                <div class="firma-cargo">Director</div>
            </div>
        </td>
        <td style="width:34%; text-align:center;">
            <div class="sello">INSTITUTE<br>TECHNOLOGY<br>IN MASTER<br>ITM</div>
            <div style="font-size:9px; color:#1a3a6b; margin-top:4px;">2321 5439</div>
        </td>
        <td style="width:33%; text-align:center;">
            <div class="firma-linea">
                <div class="firma-nombre">Santos David Rodas</div>
                <div class="firma-cargo">Instructor</div>
            </div>
        </td>
    </tr>
</table>

</body>
</html>

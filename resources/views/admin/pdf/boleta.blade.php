<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="{{ asset('images/logo_itm.jpg') }}">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; font-size:10.5px; color:#1a1a2e; padding:16px 22px; }

        .header-table { width:100%; border-collapse:collapse; margin-bottom:6px; }
        .header-logo { width:64px; height:auto; vertical-align:middle; }
        .header-table td { vertical-align:middle; }
        .inst-title { font-size:14px; font-weight:bold; color:#1a3a6b; letter-spacing:1px; text-align:center; }
        .inst-sub   { font-size:11px; font-weight:bold; color:#1a3a6b; text-align:center; }
        .divider { border:none; border-top:2px solid #1a3a6b; margin:6px 0; }
        .titulo-boleta {
            text-align:center; font-size:22px; font-weight:bold;
            color:#1a3a6b; letter-spacing:4px;
            border:2px solid #1a3a6b; padding:8px 0; margin-bottom:8px;
        }
        .intro { font-size:11px; margin-bottom:8px; line-height:1.5; }
        .dato-secundario { font-size:9px; font-weight:normal; color:#4b5563; margin-top:3px; line-height:1.4; }

        /* ── TABLA ÚNICA ── */
        .tabla-principal { width:100%; border-collapse:collapse; margin-bottom:8px; }

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

        thead { display:table-header-group; }
        .grupo-conducta, .grupo-conducta tr, .firmas-table { page-break-inside:avoid; }
        .tabla-principal td { height:auto; min-height:18px; padding:3px; line-height:1.2; overflow-wrap:break-word; }
        .aviso-prueba { padding:6px; margin-bottom:10px; border:1px solid #b0b8cc; color:#1a3a6b; font-size:9px; text-align:center; }
        .conductas-originales { margin:4px 0 6px; padding:3px 0; border-top:1px solid #b0b8cc; font-size:9px; line-height:1.4; }
        .estado-academico { margin:4px 0; font-size:11px; color:#1a3a6b; }
        .conductas-originales p { margin-top:4px; page-break-inside:avoid; }
        .texto-legal { font-size:10.5px; line-height:1.7; margin-bottom:8px; text-align:justify; }
        .firmas-table { width:100%; border-collapse:collapse; margin-top:8px; }
        .firma-linea { border-top:1px solid #1a1a2e; padding-top:4px; margin-top:8px; }
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
@php
    // Recursos locales fijos: no dependen de URLs ni de archivos enviados por el navegador.
    $logoInstituto = 'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('images/logo_itm.jpg')));
    $escudoNacional = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/escudo_el_salvador.png')));
@endphp
<table class="header-table">
    <tr>
        <td style="width:12%; text-align:center;">
            <img class="header-logo" src="{{ $logoInstituto }}" alt="Logotipo del Instituto de Computación de Aguilares">
        </td>
        <td style="width:76%; text-align:center;">
            <div class="inst-title">ITM</div>
            <div class="inst-sub">INSTITUTO DE COMPUTACIÓN DE AGUILARES</div>
        </td>
        <td style="width:12%; text-align:center;">
            <img class="header-logo" src="{{ $escudoNacional }}" alt="Escudo Nacional de El Salvador">
        </td>
    </tr>
</table>

<hr class="divider">
<div class="titulo-boleta">BOLETA DE NOTAS</div>
@if($pruebaEstructuraConducta ?? false)
<p class="aviso-prueba">MUESTRA SINTÉTICA · SIN VALIDEZ ACADÉMICA</p>
@endif

<p class="intro">
    El <strong>Instituto de Computación I. T. M</strong> hace constar que el/a alumno/a:<br>
    <strong>{{ mb_strtoupper($alumno->nombre . ' ' . $alumno->apellido) }}</strong><br>
    Código institucional: <strong>{{ $alumno->codigo ?? 'No disponible' }}</strong>
    @if(isset($curso))
    <br>Curso: <strong>{{ $curso->nombre }}</strong>
    <br>Turno: {{ $curso->nivel ?? 'No disponible' }} · Sección: {{ $curso->seccion ?? 'No disponible' }} · Año lectivo: {{ $curso->anio_lectivo ?? 'No disponible' }}
    @endif
    <br>Calificaciones registradas de las materias correspondientes al alumno y al curso:
</p>

@php
    $notas = $notas ?? $alumno->notas;
    $pruebaEstructuraConducta = $pruebaEstructuraConducta ?? false;
    // Solo registros reales del curso: no inventar materias si faltan notas.
    $bloquesConducta = $bloquesHistoricos ?? \App\Support\BoletaConductaPresentacion::bloques($notas);

    $concepto = $concepto ?? function ($p) {
        if ($p === null || $p === '') return '';
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
            <th class="th-left" style="width:32%;">Materia</th>
            <th style="width:16%;">Promedio de<br>Calificación</th>
            <th style="width:14%;">Conceptos de calificación</th>
            <th style="width:23%;">Aspectos de conducta</th>
            <th style="width:15%;">Conceptos de conducta</th>
        </tr>
    </thead>
    @foreach($bloquesConducta as $bloque)
    <tbody class="grupo-conducta">
        @foreach($bloque['notas'] as $nota)
        @php
            $nombreMateria = $nota->detalleCurso?->materia?->nombre ?? 'Materia no identificada';
            $prom = $nota->promedio;
        @endphp
        <tr>
            <td class="td-left td-materia">
                {{ $nombreMateria }}
                @if($nota->detalleCurso?->materia?->es_opcional)<span class="dato-secundario">(Opcional)</span>@endif
            </td>
            <td class="td-prom">{{ $prom ?? 'Sin nota' }}</td>
            <td class="td-conc" style="font-weight:bold; color:#1a3a6b;">{{ $concepto($prom) }}</td>
            @if($loop->first)
            <td class="td-conducta" rowspan="{{ $bloque['notas']->count() }}">{{ $bloque['aspecto'] }}</td>
            <td class="td-concepto-conducta" rowspan="{{ $bloque['notas']->count() }}">{{ $bloque['concepto'] }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
    @endforeach
    <tbody>
        @if($notas->isEmpty())
        <tr><td colspan="5">No hay calificaciones registradas para este curso.</td></tr>
        @endif

        {{-- Fila promedio final --}}
        <tr>
            <td class="td-left td-final">PROMEDIO FINAL</td>
            <td class="td-final" style="text-align:center;">
                {{ $promedio_general !== null ? round($promedio_general) : '—' }}
            </td>
            <td class="td-final"></td>
            <td colspan="2" class="td-final"></td>
        </tr>
    </tbody>
</table>

<p class="estado-academico"><strong>Estado académico del curso:</strong>
    {{ $promedio_general === null ? 'Sin calificaciones' : ($estado_academico ?? 'Pendiente de completar calificaciones') }}
</p>
<div class="conductas-originales">
    <strong>Conducta por materia</strong>
    @foreach($bloquesConducta as $bloque)
    <p>
        @foreach($bloque['notas'] as $nota)
            {{ $nota->detalleCurso?->materia?->nombre ?? 'Materia no identificada' }}: <strong>{{ \App\Support\Conducta::etiqueta($nota->conducta) }}</strong>{{ $loop->last ? '.' : ';' }}
        @endforeach
    </p>
    @endforeach
    @if($reconstruccionHistorica ?? false)
    <p>Reconstrucción basada en los datos registrados al momento de la emisión. Puede diferir del documento originalmente generado.</p>
    @else
    <p>Los aspectos son de referencia por materia. Su concepto se obtiene mediante la mediana conservadora de las evaluaciones válidas.</p>
    @endif
</div>

{{-- TEXTO LEGAL --}}
<p class="texto-legal">
    Se extiende en Aguilares, Departamento de San Salvador, a los
    <strong>{{ ($fechaBoleta ?? now())->isoFormat('D') }} días del mes de {{ ($fechaBoleta ?? now())->isoFormat('MMMM YYYY') }}</strong>.
</p>

{{-- FIRMAS --}}
<table class="firmas-table">
    <tr>
        <td style="width:33%; text-align:center;">
            <div class="firma-linea">
                @if(filled($docenteUltimaMateria ?? null))
                <div class="firma-nombre">{{ $docenteUltimaMateria }}</div>
                @endif
                <div class="firma-cargo">Docente</div>
            </div>
        </td>
        <td style="width:34%; text-align:center;">
            <div class="firma-linea" @if(!empty($firmaDirector)) style="position:relative;" @endif>
                @if(!empty($firmaDirector))
                    <img src="{{ $firmaDirector }}" alt="Firma del Director" style="position:absolute; bottom:100%; left:10%; max-width:80%; max-height:55px;">
                @endif
                @if(filled(($boletaEmitida ?? null)?->director_nombre))
                <div class="firma-nombre">{{ $boletaEmitida->director_nombre }}</div>
                @endif
                <div class="firma-cargo">Director</div>
            </div>
        </td>
        <td style="width:33%; text-align:center;">
            <div class="sello">INSTITUTE<br>TECHNOLOGY<br>IN MASTER<br>ITM</div>
            <div style="font-size:9px; color:#1a3a6b; margin-top:4px;">2321 5439</div>
        </td>
    </tr>
</table>

</body>
</html>

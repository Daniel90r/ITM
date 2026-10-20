<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing:border-box; }
        body, div, table, td { margin:0; padding:0; }
        @page { margin:28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size:10.5px; line-height:1.4; color:#263244; }

        .header { width:100%; border-collapse:collapse; margin-bottom:12px; }
        .header td { vertical-align:middle; text-align:center; }
        .logo-cell { width:72px; }
        .logo { width:64px; height:auto; }
        .inst-name { font-size:14px; font-weight:bold; color:#1a3a6b; }
        .inst-sub { font-size:9px; color:#64748b; margin-top:3px; }

        .titulo { border-top:2px solid #1a3a6b; border-bottom:1px solid #cbd5e1; color:#1a3a6b; text-align:center; padding:9px 0; font-size:16px; font-weight:bold; margin-bottom:4px; letter-spacing:1px; }
        .ciclo { text-align:center; color:#64748b; font-size:9px; margin-bottom:12px; }

        .seccion-titulo { color:#1a3a6b; background:#f1f4f8; border-bottom:1px solid #cbd5e1; padding:5px 8px; font-size:10px; font-weight:bold; margin-bottom:8px; margin-top:12px; page-break-after:avoid; }

        .campo { margin-bottom:8px; page-break-inside:avoid; }
        .campo-label { font-size:9px; color:#64748b; margin-bottom:2px; }
        .campo-valor { border-bottom:1px solid #e2e8f0; padding-bottom:4px; font-size:11px; color:#182638; min-height:16px; overflow-wrap:break-word; word-wrap:break-word; }

        .grid-2 { display:table; table-layout:fixed; width:100%; margin-bottom:8px; page-break-inside:avoid; }
        .col { display:table-cell; width:50%; padding-right:18px; vertical-align:top; }
        .col:last-child { padding-right:0; }

        .firmas { page-break-inside:avoid; }
        .firma-row { display:table; table-layout:fixed; width:100%; margin-top:8px; }
        .firma-col { display:table-cell; width:50%; text-align:center; padding:0 20px; vertical-align:bottom; }
        .firma-espacio { height:62px; }
        .firma-img { max-width:180px; max-height:58px; width:auto; height:auto; }
        .firma-linea { border-top:1px solid #64748b; padding-top:5px; font-size:10px; color:#475569; margin-top:4px; }

        .footer { text-align:center; margin-top:16px; border-top:1px solid #cbd5e1; padding-top:7px; font-size:8px; color:#64748b; page-break-inside:avoid; }
    </style>
</head>
<body>

    @php
        $logoInstitucional = 'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('images/logo_itm.jpg')));
    @endphp
    <table class="header"><tr>
        <td class="logo-cell"><img class="logo" src="{{ $logoInstitucional }}" alt="Logotipo del Instituto de Computación de Aguilares"></td>
        <td>
        <div class="inst-name">Instituto de Computación de Aguilares</div>
        <div class="inst-sub">I.T.M. Aguilares · Sistema de Gestión Académica</div>
        </td>
        <td class="logo-cell"></td>
    </tr></table>

    <div class="titulo">SOLICITUD DE INSCRIPCIÓN</div>
    <div class="ciclo">Ciclo {{ $fecha->year }}</div>

    {{-- Datos Personales --}}
    <div class="seccion-titulo">I. DATOS PERSONALES DEL ALUMNO</div>

    <div class="campo">
        <div class="campo-label">Nombre completo del alumno</div>
        <div class="campo-valor">{{ $alumno->nombre_completo }}</div>
    </div>

    <div class="grid-2">
        <div class="col">
            <div class="campo-label">Código / Matrícula</div>
            <div class="campo-valor">{{ $alumno->codigo }}</div>
        </div>
        <div class="col">
            <div class="campo-label">Fecha de nacimiento</div>
            <div class="campo-valor">
                {{ $alumno->fecha_nacimiento ? \Carbon\Carbon::parse($alumno->fecha_nacimiento)->isoFormat('D [de] MMMM [de] YYYY') : '—' }}
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="col">
            <div class="campo-label">Teléfono</div>
            <div class="campo-valor">{{ $alumno->telefono ?? '—' }}</div>
        </div>
        <div class="col">
            <div class="campo-label">Sexo</div>
            <div class="campo-valor">{{ $alumno->genero === 'M' ? 'Masculino' : ($alumno->genero === 'F' ? 'Femenino' : '—') }}</div>
        </div>
    </div>

    <div class="campo">
        <div class="campo-label">Dirección</div>
        <div class="campo-valor">{{ $alumno->direccion ?? '—' }}</div>
    </div>

    {{-- Datos del Encargado / Documento de Identidad --}}
    @if($alumno->es_mayor_de_edad)


<div class="seccion-titulo">II. DOCUMENTO DE IDENTIDAD</div>


<div class="campo">
    <div class="campo-label">DUI del alumno</div>
    <div class="campo-valor">
        {{ $alumno->dui }}
    </div>
</div>


    @else


<div class="seccion-titulo">II. DATOS DEL ENCARGADO O RESPONSABLE</div>


<div class="campo">
    <div class="campo-label">Nombre</div>
    <div class="campo-valor">
        {{ $encargado_nombre }} {{ $encargado_apellido }}
    </div>
</div>


<div class="campo">
    <div class="campo-label">Parentesco</div>
    <div class="campo-valor">
        {{ $encargado_parentesco }}
    </div>
</div>


<div class="campo">
    <div class="campo-label">Teléfono</div>
    <div class="campo-valor">
        {{ $encargado_telefono ?? '—' }}
    </div>
</div>


<div class="campo">
    <div class="campo-label">DUI</div>
    <div class="campo-valor">
        {{ $encargado_dui ?? '—' }}
    </div>
</div>


    @endif

    {{-- Sección seleccionada --}}
    <div class="seccion-titulo">III. INFORMACIÓN ACADÉMICA Y CURSO SELECCIONADO</div>

    @if($curso)
    <div class="grid-2">
        <div class="col">
            <div class="campo-label">Sección</div>
            <div class="campo-valor">{{ $curso->nombre }} — Sección {{ $curso->seccion }}</div>
        </div>
        <div class="col">
            <div class="campo-label">Turno</div>
            <div class="campo-valor">{{ $curso->nivel }}</div>
        </div>
    </div>

    @else
        @forelse($cursosDisponibles as $disponible)
            <div class="campo">{{ $disponible->nombre }} — Sección {{ $disponible->seccion }} — {{ $disponible->nivel }}</div>
        @empty
            <div class="campo">No hay cursos disponibles.</div>
        @endforelse
    @endif

    {{-- Inicio de clases --}}
    <div class="seccion-titulo">IV. FECHAS Y HORARIOS</div>

    <div class="grid-2">
        <div class="col">
            <div class="campo-label">Fecha de solicitud</div>
            <div class="campo-valor">{{ $fecha->isoFormat('D [de] MMMM [de] YYYY') }}</div>
        </div>
        <div class="col">
            <div class="campo-label">Horario</div>
            <div class="campo-valor">
                @foreach(app(\App\Services\HorariosCurso::class)->lineas($curso) as $linea)
                    <div>{{ $linea }}</div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Firmas --}}
    <div class="firmas">
    <div class="seccion-titulo">V. FIRMAS</div>
    <div class="firma-row">
        <div class="firma-col">
            <div class="firma-espacio">
            @if(!empty($firma_alumno))
                <img src="{{ $firma_alumno }}" class="firma-img" alt="Firma del alumno">
            @endif
            </div>
            <div class="firma-linea">Firma del Alumno</div>
        </div>
        @if(!$alumno->es_mayor_de_edad)
        <div class="firma-col">
            <div class="firma-espacio">
            @if(!empty($firma_encargado))
                <img src="{{ $firma_encargado }}" class="firma-img" alt="Firma del encargado">
            @endif
            </div>
            <div class="firma-linea">Firma del Encargado</div>
        </div>
        @endif
    </div>
    </div>

    <div class="footer">
        I.T.M. — Instituto de Computación de Aguilares · Ciclo {{ $fecha->year }}<br>
        Generado digitalmente el {{ $fecha->isoFormat('D [de] MMMM [de] YYYY [a las] HH:mm') }}
    </div>

</body>
</html>

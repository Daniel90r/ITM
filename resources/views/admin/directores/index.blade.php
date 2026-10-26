<x-app-layout>
    <div style="display:flex; height:100vh; overflow:hidden;">
        @include('components.admin-sidebar', ['active' => 'directores'])

        <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

            {{-- ENCABEZADO --}}
            <header style="
                background:#ffffff;
                border-bottom:1px solid #e5e7eb;
                padding:14px 24px;
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:20px;
                flex-shrink:0;
            ">
                <div>
                    <h1 style="
                        margin:0;
                        font-size:18px;
                        font-weight:700;
                        color:#111827;
                    ">
                        Gestión de Directores
                    </h1>

                    <p style="
                        margin:3px 0 0;
                        font-size:12px;
                        color:#6b7280;
                    ">
                        Administra el Director vigente y consulta el historial institucional.
                    </p>
                </div>

                <button
                    type="button"
                    onclick="abrirModalDirector()"
                    style="
                        background:#111827;
                        border:none;
                        padding:9px 18px;
                        border-radius:8px;
                        color:#ffffff;
                        font-size:12px;
                        font-weight:600;
                        cursor:pointer;
                        white-space:nowrap;
                    "
                >
                    {{ $directorActual ? 'Nombrar nuevo Director' : 'Registrar Director' }}
                </button>
            </header>

            {{-- CONTENIDO --}}
            <main style="
                flex:1;
                overflow-y:auto;
                padding:24px;
            ">
                <div style="
                    width:100%;
                    max-width:1400px;
                    margin:0 auto;
                ">

                    {{-- MENSAJE DE ÉXITO --}}
                    @if (session('success'))
                        <div style="
                            margin-bottom:18px;
                            padding:12px 16px;
                            background:#ecfdf5;
                            border:1px solid #a7f3d0;
                            border-radius:10px;
                            color:#065f46;
                            font-size:13px;
                        ">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- DIRECTOR ACTUAL --}}
                    <section style="
                        background:#ffffff;
                        border:1px solid #e5e7eb;
                        border-radius:14px;
                        overflow:hidden;
                        box-shadow:0 1px 4px rgba(0,0,0,0.04);
                        margin-bottom:24px;
                    ">
                        <div style="
                            padding:16px 24px;
                            border-bottom:1px solid #e5e7eb;
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            gap:16px;
                        ">
                            <div style="
                                display:flex;
                                align-items:center;
                                gap:10px;
                            ">
                                <span style="
                                    width:4px;
                                    height:28px;
                                    border-radius:4px;
                                    background:#111827;
                                    display:block;
                                "></span>

                                <div>
                                    <h2 style="
                                        margin:0;
                                        font-size:15px;
                                        font-weight:700;
                                        color:#111827;
                                    ">
                                        Director actual
                                    </h2>

                                    <p style="
                                        margin:3px 0 0;
                                        font-size:12px;
                                        color:#6b7280;
                                    ">
                                        Información del Director vigente de la institución.
                                    </p>
                                </div>
                            </div>

                            @if ($directorActual)
                                <span style="
                                    display:inline-flex;
                                    align-items:center;
                                    padding:4px 10px;
                                    border-radius:999px;
                                    background:#dcfce7;
                                    color:#166534;
                                    font-size:11px;
                                    font-weight:600;
                                ">
                                    Vigente
                                </span>
                            @endif
                        </div>

                        <div style="padding:24px;">
                            @if ($directorActual)

                                <div style="
                                    display:grid;
                                    grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));
                                    gap:22px;
                                ">
                                    <div>
                                        <p style="
                                            margin:0 0 5px;
                                            font-size:10px;
                                            font-weight:600;
                                            color:#9ca3af;
                                            text-transform:uppercase;
                                            letter-spacing:.05em;
                                        ">
                                            Nombre
                                        </p>

                                        <p style="
                                            margin:0;
                                            font-size:14px;
                                            font-weight:600;
                                            color:#111827;
                                        ">
                                            {{ $directorActual->nombre }}
                                        </p>
                                    </div>

                                    <div>
                                        <p style="
                                            margin:0 0 5px;
                                            font-size:10px;
                                            font-weight:600;
                                            color:#9ca3af;
                                            text-transform:uppercase;
                                            letter-spacing:.05em;
                                        ">
                                            Fecha de inicio
                                        </p>

                                        <p style="
                                            margin:0;
                                            font-size:13px;
                                            color:#374151;
                                        ">
                                            {{ $directorActual->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                        </p>
                                    </div>

                                    @if ($directorActual->dui)
                                        <div>
                                            <p style="
                                                margin:0 0 5px;
                                                font-size:10px;
                                                font-weight:600;
                                                color:#9ca3af;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                            ">
                                                DUI
                                            </p>

                                            <p style="
                                                margin:0;
                                                font-size:13px;
                                                color:#374151;
                                            ">
                                                {{ $directorActual->dui }}
                                            </p>
                                        </div>
                                    @endif

                                    @if ($directorActual->telefono)
                                        <div>
                                            <p style="
                                                margin:0 0 5px;
                                                font-size:10px;
                                                font-weight:600;
                                                color:#9ca3af;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                            ">
                                                Teléfono
                                            </p>

                                            <p style="
                                                margin:0;
                                                font-size:13px;
                                                color:#374151;
                                            ">
                                                {{ $directorActual->telefono }}
                                            </p>
                                        </div>
                                    @endif

                                    @if ($directorActual->email)
                                        <div>
                                            <p style="
                                                margin:0 0 5px;
                                                font-size:10px;
                                                font-weight:600;
                                                color:#9ca3af;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                            ">
                                                Correo
                                            </p>

                                            <p style="
                                                margin:0;
                                                font-size:13px;
                                                color:#374151;
                                                word-break:break-word;
                                            ">
                                                {{ $directorActual->email }}
                                            </p>
                                        </div>
                                    @endif

<div>
                        <p style="
                            margin:0 0 5px;
                            font-size:10px;
                            font-weight:600;
                            color:#9ca3af;
                            text-transform:uppercase;
                            letter-spacing:.05em;
                        ">
                            Cuenta vinculada
                        </p>

                        @if ($directorActual->user)
                            <p style="
                                margin:0;
                                font-size:13px;
                                font-weight:600;
                                color:#374151;
                            ">
                                {{ $directorActual->user->name }}
                            </p>

                            <p style="
                                margin:2px 0 0;
                                font-size:11px;
                                color:#9ca3af;
                            ">
                                {{ $directorActual->user->email }}
                            </p>
                        @else
                            <p style="
                                margin:0;
                                font-size:12px;
                                color:#9ca3af;
                            ">
                                No posee una cuenta administrativa vinculada.
                            </p>
                        @endif
                    </div>

                    @if ($directorActual->firma_path)
                    <div>
                        <p style="
                            margin:0 0 5px;
                            font-size:10px;
                            font-weight:600;
                            color:#9ca3af;
                            text-transform:uppercase;
                            letter-spacing:.05em;
                        ">
                            Firma
                        </p>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="{{ route('admin.directores.firma.show', $directorActual) }}"
                                 alt="Firma de {{ $directorActual->nombre }}"
                                 style="max-height:60px; max-width:200px; border:1px solid #e5e7eb; border-radius:6px;">
                        </div>
                    </div>
                    @else
                    <div>
                        <p style="
                            margin:0 0 5px;
                            font-size:10px;
                            font-weight:600;
                            color:#9ca3af;
                            text-transform:uppercase;
                            letter-spacing:.05em;
                        ">
                            Firma
                        </p>
                        <p style="margin:0; font-size:13px; color:#9ca3af;">Sin firma</p>
                    </div>
                    @endif

                    <div style="margin-top:16px; padding-top:16px; border-top:1px solid #e5e7eb;">
                        <form method="POST" action="{{ route('admin.directores.firma.update', $directorActual) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')
                            <x-firma-director contexto="actualizacion" bolsa-errores="firmaDirector" />
                            <button type="submit" style="background:#111827; border:none; padding:8px 16px; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;">Actualizar firma</button>
                        </form>
                    </div>
                </div>

                            @else

                                <div style="
                                    padding:34px 20px;
                                    text-align:center;
                                ">
                                    <div style="
                                        width:48px;
                                        height:48px;
                                        margin:0 auto 12px;
                                        border-radius:50%;
                                        background:#f3f4f6;
                                        display:flex;
                                        align-items:center;
                                        justify-content:center;
                                        color:#9ca3af;
                                    ">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="22"
                                            height="22"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle cx="12" cy="7" r="4"></circle>
                                            <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
                                        </svg>
                                    </div>

                                    <p style="
                                        margin:0;
                                        font-size:14px;
                                        font-weight:600;
                                        color:#374151;
                                    ">
                                        No hay un Director vigente registrado.
                                    </p>

                                    <p style="
                                        margin:6px 0 0;
                                        font-size:12px;
                                        color:#9ca3af;
                                    ">
                                        Registra el primer Director para comenzar el historial institucional.
                                    </p>
                                </div>

                            @endif
                        </div>
                    </section>

                    {{-- HISTORIAL --}}
                    <section style="
                        background:#ffffff;
                        border:1px solid #e5e7eb;
                        border-radius:14px;
                        overflow:hidden;
                        box-shadow:0 1px 4px rgba(0,0,0,0.04);
                    ">
                        <div style="
                            padding:16px 24px;
                            border-bottom:1px solid #e5e7eb;
                            display:flex;
                            align-items:center;
                            gap:10px;
                        ">
                            <span style="
                                width:4px;
                                height:28px;
                                border-radius:4px;
                                background:#111827;
                                display:block;
                            "></span>

                            <div>
                                <h2 style="
                                    margin:0;
                                    font-size:15px;
                                    font-weight:700;
                                    color:#111827;
                                ">
                                    Historial de Directores
                                </h2>

                                <p style="
                                    margin:3px 0 0;
                                    font-size:12px;
                                    color:#6b7280;
                                ">
                                    Consulta los períodos anteriores de Dirección.
                                </p>
                            </div>
                        </div>

                        @if ($historial->count() > 0)

                            <div style="overflow-x:auto;">
                                <table style="
                                    width:100%;
                                    border-collapse:collapse;
                                ">
                                    <thead style="background:#f9fafb;">
                                        <tr>
                                            <th style="
                                                padding:11px 24px;
                                                text-align:left;
                                                font-size:11px;
                                                font-weight:600;
                                                color:#6b7280;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                                border-bottom:1px solid #e5e7eb;
                                            ">
                                                Nombre
                                            </th>

                                            <th style="
                                                padding:11px 24px;
                                                text-align:left;
                                                font-size:11px;
                                                font-weight:600;
                                                color:#6b7280;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                                border-bottom:1px solid #e5e7eb;
                                            ">
                                                Período
                                            </th>

                                            <th style="
                                                padding:11px 24px;
                                                text-align:left;
                                                font-size:11px;
                                                font-weight:600;
                                                color:#6b7280;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                                border-bottom:1px solid #e5e7eb;
                                            ">
                                                DUI
                                            </th>

                                            <th style="
                                                padding:11px 24px;
                                                text-align:left;
                                                font-size:11px;
                                                font-weight:600;
                                                color:#6b7280;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                                border-bottom:1px solid #e5e7eb;
                                            ">
                                                Teléfono
                                            </th>

                                            <th style="
                                                padding:11px 24px;
                                                text-align:left;
                                                font-size:11px;
                                                font-weight:600;
                                                color:#6b7280;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                                border-bottom:1px solid #e5e7eb;
                                            ">
                                                Correo
                                            </th>

                                            <th style="
                                                padding:11px 24px;
                                                text-align:left;
                                                font-size:11px;
                                                font-weight:600;
                                                color:#6b7280;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                                border-bottom:1px solid #e5e7eb;
                                            ">
                                                Cuenta vinculada
                                            </th>

                                            <th style="
                                                padding:11px 24px;
                                                text-align:left;
                                                font-size:11px;
                                                font-weight:600;
                                                color:#6b7280;
                                                text-transform:uppercase;
                                                letter-spacing:.05em;
                                                border-bottom:1px solid #e5e7eb;
                                            ">
                                                Estado
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($historial as $director)
                                            <tr>
                                                <td style="
                                                    padding:14px 24px;
                                                    border-bottom:1px solid #f3f4f6;
                                                    font-size:13px;
                                                    font-weight:600;
                                                    color:#111827;
                                                    white-space:nowrap;
                                                ">
                                                    {{ $director->nombre }}
                                                </td>

                                                <td style="
                                                    padding:14px 24px;
                                                    border-bottom:1px solid #f3f4f6;
                                                    font-size:12px;
                                                    color:#4b5563;
                                                    white-space:nowrap;
                                                ">
                                                    {{ $director->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                                    —
                                                    {{ $director->fecha_fin?->format('d/m/Y') ?? 'Actual' }}
                                                </td>

                                                <td style="
                                                    padding:14px 24px;
                                                    border-bottom:1px solid #f3f4f6;
                                                    font-size:12px;
                                                    color:#4b5563;
                                                    white-space:nowrap;
                                                ">
                                                    {{ $director->dui ?: '—' }}
                                                </td>

                                                <td style="
                                                    padding:14px 24px;
                                                    border-bottom:1px solid #f3f4f6;
                                                    font-size:12px;
                                                    color:#4b5563;
                                                    white-space:nowrap;
                                                ">
                                                    {{ $director->telefono ?: '—' }}
                                                </td>

                                                <td style="
                                                    padding:14px 24px;
                                                    border-bottom:1px solid #f3f4f6;
                                                    font-size:12px;
                                                    color:#4b5563;
                                                ">
                                                    {{ $director->email ?: '—' }}
                                                </td>

                                                <td style="
                                                    padding:14px 24px;
                                                    border-bottom:1px solid #f3f4f6;
                                                    font-size:12px;
                                                    color:#4b5563;
                                                ">
                                                    @if ($director->user)
                                                        <div style="font-weight:600;">
                                                            {{ $director->user->name }}
                                                        </div>

                                                        <div style="
                                                            margin-top:2px;
                                                            font-size:10px;
                                                            color:#9ca3af;
                                                        ">
                                                            {{ $director->user->email }}
                                                        </div>
                                                    @else
                                                        <span style="color:#9ca3af;">
                                                            Sin cuenta vinculada
                                                        </span>
                                                    @endif
                                                </td>

                                                <td style="
                                                    padding:14px 24px;
                                                    border-bottom:1px solid #f3f4f6;
                                                    white-space:nowrap;
                                                ">
                                                    <span style="
                                                        display:inline-flex;
                                                        padding:4px 10px;
                                                        border-radius:999px;
                                                        background:#f3f4f6;
                                                        color:#6b7280;
                                                        font-size:10px;
                                                        font-weight:600;
                                                    ">
                                                        Finalizado
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($historial->hasPages())
                                <div style="
                                    padding:14px 24px;
                                    border-top:1px solid #e5e7eb;
                                ">
                                    {{ $historial->links() }}
                                </div>
                            @endif

                        @else

                            <div style="
                                padding:40px;
                                text-align:center;
                                color:#9ca3af;
                                font-size:13px;
                            ">
                                Aún no hay Directores anteriores registrados.
                            </div>

                        @endif
                    </section>
                </div>
            </main>
        </div>
    </div>

    {{-- MODAL DIRECTOR --}}
    <div
        id="modalDirector"
        style="
            display:none;
            position:fixed;
            inset:0;
            z-index:9999;
            background:rgba(17,24,39,.55);
            align-items:center;
            justify-content:center;
            padding:20px;
        "
    >
        <div style="
            width:100%;
            max-width:620px;
            max-height:90vh;
            overflow-y:auto;
            background:#ffffff;
            border-radius:14px;
            box-shadow:0 24px 60px rgba(0,0,0,.25);
        ">
            <div style="
                padding:16px 20px;
                border-bottom:1px solid #e5e7eb;
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:16px;
            ">
                <div>
                    <h2 style="
                        margin:0;
                        font-size:15px;
                        font-weight:700;
                        color:#111827;
                    ">
                        {{ $directorActual ? 'Nombrar nuevo Director' : 'Registrar Director' }}
                    </h2>

                    <p style="
                        margin:3px 0 0;
                        font-size:11px;
                        color:#6b7280;
                    ">
                        Completa la información institucional del Director.
                    </p>
                </div>

                <button
                    type="button"
                    onclick="cerrarModalDirector()"
                    style="
                        width:32px;
                        height:32px;
                        border:none;
                        border-radius:8px;
                        background:#f3f4f6;
                        color:#6b7280;
                        cursor:pointer;
                        font-size:16px;
                    "
                    aria-label="Cerrar"
                >
                    ×
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('admin.directores.store') }}"
                enctype="multipart/form-data"
            >
                @csrf

                <div style="padding:20px;">

                    @if ($directorActual)
                        <div style="
                            margin-bottom:16px;
                            padding:11px 14px;
                            background:#fffbeb;
                            border:1px solid #fde68a;
                            border-radius:8px;
                            color:#92400e;
                            font-size:12px;
                            line-height:1.5;
                        ">
                            Al registrar un nuevo Director, el período del Director actual se cerrará automáticamente y permanecerá disponible en el historial.
                        </div>
                    @endif

                    @if ($errors->any())
                        <div style="
                            margin-bottom:16px;
                            padding:12px 14px;
                            background:#fef2f2;
                            border:1px solid #fecaca;
                            border-radius:8px;
                            color:#991b1b;
                            font-size:12px;
                        ">
                            <strong>Revisa la información ingresada:</strong>

                            <ul style="
                                margin:7px 0 0 18px;
                                padding:0;
                            ">
                                @foreach ($errors->all() as $error)
                                    <li style="margin-bottom:3px;">
                                        {{ $error }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div style="margin-bottom:15px;">
                        <label
                            for="nombre"
                            style="
                                display:block;
                                margin-bottom:6px;
                                font-size:12px;
                                font-weight:600;
                                color:#374151;
                            "
                        >
                            Nombre completo
                            <span style="color:#dc2626;">*</span>
                        </label>

                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            required
                            maxlength="100"
                            value="{{ old('nombre') }}"
                            style="
                                width:100%;
                                border:1px solid #d1d5db;
                                border-radius:8px;
                                padding:9px 11px;
                                font-size:13px;
                                color:#111827;
                                outline:none;
                                box-sizing:border-box;
                            "
                        >
                    </div>

                    <div style="
                        display:grid;
                        grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
                        gap:14px;
                        margin-bottom:15px;
                    ">
                        <div>
                            <label
                                for="dui"
                                style="
                                    display:block;
                                    margin-bottom:6px;
                                    font-size:12px;
                                    font-weight:600;
                                    color:#374151;
                                "
                            >
                                DUI
                            </label>

                            <p id="director-dui-error" role="alert" hidden style="color:#b91c1c;font-size:12px;margin:6px 0;"></p>
                            <input
                                id="dui"
                                name="dui"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]{8}-[0-9]"
                                data-director-dui
                                data-dui-error="director-dui-error"
                                aria-describedby="director-dui-error"
                                value="{{ old('dui') }}"
                                placeholder="00000000-0"
                                maxlength="10"
                                style="
                                    width:100%;
                                    border:1px solid #d1d5db;
                                    border-radius:8px;
                                    padding:9px 11px;
                                    font-size:13px;
                                    color:#111827;
                                    outline:none;
                                    box-sizing:border-box;
                                "
                            >
                        </div>

                        <div>
                            <label
                                for="telefono"
                                style="
                                    display:block;
                                    margin-bottom:6px;
                                    font-size:12px;
                                    font-weight:600;
                                    color:#374151;
                                "
                            >
                                Teléfono
                            </label>

                            <input
                                id="telefono"
                                name="telefono"
                                type="text"
                                value="{{ old('telefono') }}"
                                placeholder="0000-0000"
                                maxlength="9"
                                data-phone-mask
                                style="
                                    width:100%;
                                    border:1px solid #d1d5db;
                                    border-radius:8px;
                                    padding:9px 11px;
                                    font-size:13px;
                                    color:#111827;
                                    outline:none;
                                    box-sizing:border-box;
                                "
                            >
                        </div>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label
                            for="email"
                            style="
                                display:block;
                                margin-bottom:6px;
                                font-size:12px;
                                font-weight:600;
                                color:#374151;
                            "
                        >
                            Correo
                        </label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            style="
                                width:100%;
                                border:1px solid #d1d5db;
                                border-radius:8px;
                                padding:9px 11px;
                                font-size:13px;
                                color:#111827;
                                outline:none;
                                box-sizing:border-box;
                            "
                        >
                    </div>

                    <div style="margin-bottom:15px;">
                        <label
                            for="user_id"
                            style="
                                display:block;
                                margin-bottom:6px;
                                font-size:12px;
                                font-weight:600;
                                color:#374151;
                            "
                        >
                            Cuenta administrativa vinculada
                            <span style="
                                font-weight:400;
                                color:#9ca3af;
                            ">
                                (opcional)
                            </span>
                        </label>

                        <select
                            id="user_id"
                            name="user_id"
                            style="
                                width:100%;
                                border:1px solid #d1d5db;
                                border-radius:8px;
                                padding:9px 11px;
                                font-size:13px;
                                color:#111827;
                                background:#ffffff;
                                outline:none;
                                box-sizing:border-box;
                            "
                        >
                            <option value="">
                                Sin cuenta vinculada
                            </option>

                            @foreach ($usersAdmin as $user)
                                <option
                                    value="{{ $user->id }}"
                                    @selected((string) old('user_id') === (string) $user->id)
                                >
                                    {{ $user->name }} — {{ $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label
                            for="fecha_inicio"
                            style="
                                display:block;
                                margin-bottom:6px;
                                font-size:12px;
                                font-weight:600;
                                color:#374151;
                            "
                        >
                            Fecha de inicio
                            <span style="color:#dc2626;">*</span>
                        </label>

                        <input
                            id="fecha_inicio"
                            name="fecha_inicio"
                            type="date"
                            required
                            value="{{ old('fecha_inicio') }}"
                            style="
                                width:100%;
                                border:1px solid #d1d5db;
                                border-radius:8px;
                                padding:9px 11px;
                                font-size:13px;
                                color:#111827;
                                outline:none;
                                box-sizing:border-box;
                            "
                        >
                    </div>

                    <x-firma-director contexto="registro" />
                </div>

                <div style="
                    padding:14px 20px;
                    border-top:1px solid #e5e7eb;
                    display:flex;
                    justify-content:flex-end;
                    gap:10px;
                    background:#f9fafb;
                ">
                    <button
                        type="button"
                        onclick="cerrarModalDirector()"
                        style="
                            background:#ffffff;
                            border:1px solid #d1d5db;
                            padding:8px 16px;
                            border-radius:8px;
                            color:#374151;
                            font-size:12px;
                            font-weight:600;
                            cursor:pointer;
                        "
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        style="
                            background:#111827;
                            border:none;
                            padding:8px 18px;
                            border-radius:8px;
                            color:#ffffff;
                            font-size:12px;
                            font-weight:600;
                            cursor:pointer;
                        "
                    >
                        Guardar Director
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalDirector() {
            const modal = document.getElementById('modalDirector');

            if (!modal) {
                return;
            }

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function cerrarModalDirector() {
            const modal = document.getElementById('modalDirector');

            if (!modal) {
                return;
            }

            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('modalDirector');

            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        cerrarModalDirector();
                    }
                });
            }

            @if ($errors->any())
                abrirModalDirector();
            @endif
        });
    </script>
</x-app-layout>

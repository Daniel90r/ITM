<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-institutional-favicon />
    <title>Oferta académica · ITM Aguilares</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #6366f1 0%, #38bdf8 50%, #7c3aed 100%);
            background-attachment: fixed;
            color: #fff;
        }
        a { color: inherit; text-decoration: none; }
        .contenedor { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        .vidrio {
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 18px;
        }
        .boton {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 11px 20px; border-radius: 10px; font-size: 14px; font-weight: 700;
            transition: opacity .15s, transform .15s; cursor: pointer; border: none;
        }
        .boton:hover { opacity: .88; transform: translateY(-1px); }
        .boton-principal { background: linear-gradient(90deg,#f59e0b,#fbbf24); color: #3b0764; }
        .boton-secundario { background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.35); color: #fff; }
        .boton:focus-visible { outline: 3px solid #fde68a; outline-offset: 2px; }

        /* Encabezado */
        .barra { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 0; }
        .marca { display: flex; align-items: center; gap: 12px; }
        .marca img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid #f59e0b; background: #fff; }
        .marca strong { display: block; font-size: 16px; }
        .marca span { font-size: 12px; color: rgba(255,255,255,0.75); }

        /* Portada */
        .portada { padding: 36px 0 28px; display: grid; grid-template-columns: 1.4fr 1fr; gap: 24px; align-items: center; }
        .portada h1 { font-size: 40px; line-height: 1.15; margin-bottom: 14px; }
        .portada h1 em { font-style: normal; color: #fde68a; }
        .portada p { font-size: 16px; line-height: 1.6; color: rgba(255,255,255,0.88); margin-bottom: 22px; max-width: 560px; }
        .acciones { display: flex; flex-wrap: wrap; gap: 12px; }
        .datos { padding: 22px; display: grid; gap: 14px; }
        .dato { display: flex; align-items: center; gap: 14px; }
        .dato b { font-size: 28px; color: #fde68a; min-width: 56px; }
        .dato span { font-size: 14px; color: rgba(255,255,255,0.88); }

        /* Cursos */
        .seccion-titulo { font-size: 24px; margin: 28px 0 6px; }
        .seccion-sub { font-size: 14px; color: rgba(255,255,255,0.8); margin-bottom: 18px; }
        .cursos { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 18px; }
        .curso { padding: 22px; display: flex; flex-direction: column; gap: 14px; }
        .curso-cabeza { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .curso h3 { font-size: 19px; line-height: 1.3; }
        .curso .meta { font-size: 13px; color: rgba(255,255,255,0.78); margin-top: 4px; }
        .insignia { font-size: 11px; font-weight: 700; padding: 5px 10px; border-radius: 999px; white-space: nowrap; }
        .insignia-abierta { background: #dcfce7; color: #166534; }
        .insignia-curso { background: #e0e7ff; color: #3730a3; }
        .insignia-llena { background: #fee2e2; color: #991b1b; }
        .etiqueta { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #fde68a; margin-bottom: 6px; }
        .materias { display: flex; flex-wrap: wrap; gap: 6px; }
        .materia { font-size: 12px; padding: 5px 10px; border-radius: 8px; background: rgba(255,255,255,0.16); }
        .materia small { color: #fde68a; }
        .lista { list-style: none; font-size: 13px; color: rgba(255,255,255,0.9); display: grid; gap: 3px; }
        .curso-pie { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.18); }
        .cupos { font-size: 13px; color: rgba(255,255,255,0.85); }
        .vacio { padding: 28px; text-align: center; font-size: 15px; color: rgba(255,255,255,0.9); }

        /* Pasos */
        .pasos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 30px; }
        .paso { padding: 22px; }
        .paso .numero { width: 36px; height: 36px; border-radius: 50%; background: #f59e0b; color: #3b0764; font-weight: 800; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .paso h4 { font-size: 16px; margin-bottom: 6px; }
        .paso p { font-size: 13px; line-height: 1.55; color: rgba(255,255,255,0.85); }

        .pie { padding: 22px 0 30px; font-size: 12px; color: rgba(255,255,255,0.7); text-align: center; line-height: 1.7; }

        @media (max-width: 820px) {
            .portada { grid-template-columns: 1fr; padding-top: 16px; }
            .portada h1 { font-size: 30px; }
            .pasos { grid-template-columns: 1fr; }
            .marca span { display: none; }
        }
        @media (max-width: 420px) {
            .cursos { grid-template-columns: 1fr; }
            .barra .boton { padding: 9px 14px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <header class="barra">
            <a href="{{ route('inicio') }}" class="marca">
                <img src="{{ asset('images/logo_itm.jpg') }}" alt="Logo del ITM Aguilares">
                <div>
                    <strong>ITM Aguilares</strong>
                    <span>Instituto Técnico de Computación de Aguilares</span>
                </div>
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="boton boton-principal">Ir a mi panel</a>
            @else
                <a href="{{ route('login') }}" class="boton boton-principal">Iniciar sesión</a>
            @endauth
        </header>

        <section class="portada">
            <div>
                <h1>Aprende computación e inglés en el <em>ITM Aguilares</em></h1>
                <p>Formamos a jóvenes desde los 14 años y a adultos en informática y, como complemento, en inglés. Conoce los cursos disponibles, sus materias y horarios, y solicita tu inscripción en línea.</p>
                <div class="acciones">
                    <a href="#oferta" class="boton boton-principal">Ver oferta académica</a>
                    <a href="{{ route('alumno.inscripcion') }}" class="boton boton-secundario">Inscribirme en línea</a>
                </div>
            </div>
            <div class="vidrio datos">
                <div class="dato"><b>10</b><span>años de trayectoria en Aguilares</span></div>
                <div class="dato"><b>2</b><span>programas: informática (2 años) e inglés (6 meses)</span></div>
                <div class="dato"><b>{{ \App\Models\Curso::CUPOS_MAXIMO }}</b><span>cupos como máximo por curso, para una atención cercana</span></div>
            </div>
        </section>

        <section id="oferta">
            <h2 class="seccion-titulo">Oferta académica {{ now()->year }}</h2>
            <p class="seccion-sub">Cursos vigentes del instituto. Las inscripciones están abiertas en los cursos que todavía no han iniciado.</p>

            @if ($cursos->isEmpty())
                <div class="vidrio vacio">
                    Por el momento no hay cursos publicados. Vuelve pronto o visita el instituto para más información.
                </div>
            @else
                <div class="cursos">
                    @foreach ($cursos as $curso)
                        <article class="vidrio curso">
                            <div class="curso-cabeza">
                                <div>
                                    <h3>{{ $curso->nombre }}</h3>
                                    <p class="meta">
                                        {{ $curso->nivel }} · Sección {{ $curso->seccion }} · {{ $curso->anio_lectivo }}
                                    </p>
                                </div>
                                @if ($curso->inscripcion_abierta)
                                    <span class="insignia insignia-abierta">Inscripciones abiertas</span>
                                @elseif ($curso->estado_curso === \App\Models\Curso::ESTADO_PROXIMO && $curso->esta_lleno)
                                    <span class="insignia insignia-llena">Cupo lleno</span>
                                @else
                                    <span class="insignia insignia-curso">En curso</span>
                                @endif
                            </div>

                            @if ($curso->fecha_inicio && $curso->fecha_fin)
                                <div>
                                    <p class="etiqueta">Fechas</p>
                                    <p class="lista">Del {{ $curso->fecha_inicio->format('d/m/Y') }} al {{ $curso->fecha_fin->format('d/m/Y') }}</p>
                                </div>
                            @endif

                            <div>
                                <p class="etiqueta">Materias</p>
                                @if ($curso->materias_publicas->isEmpty())
                                    <p class="lista">Materias por confirmar</p>
                                @else
                                    <div class="materias">
                                        @foreach ($curso->materias_publicas as $materia)
                                            <span class="materia">{{ $materia->nombre }}@if ($materia->es_opcional) <small>(opcional)</small>@endif</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                                <p class="etiqueta">Horario</p>
                                <ul class="lista">
                                    @foreach ($curso->horario_publico as $linea)
                                        <li>{{ $linea }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="curso-pie">
                                @if ($curso->inscripcion_abierta)
                                    <span class="cupos">{{ $curso->cupos_disponibles }} cupos disponibles</span>
                                    <a href="{{ route('alumno.inscripcion') }}" class="boton boton-principal">Inscribirme</a>
                                @else
                                    <span class="cupos">Consulta próximas aperturas en el instituto</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section>
            <h2 class="seccion-titulo">¿Cómo me inscribo?</h2>
            <p class="seccion-sub">La solicitud se hace en línea desde el portal del alumno.</p>
            <div class="pasos">
                <div class="vidrio paso">
                    <div class="numero">1</div>
                    <h4>Obtén tu usuario</h4>
                    <p>Visita el instituto con tus datos. La administración crea tu cuenta de alumno y te entrega el correo y la contraseña de acceso.</p>
                </div>
                <div class="vidrio paso">
                    <div class="numero">2</div>
                    <h4>Envía tu solicitud</h4>
                    <p>Inicia sesión, elige un curso con inscripciones abiertas, completa el formulario con tu firma y adjunta los documentos que se piden.</p>
                </div>
                <div class="vidrio paso">
                    <div class="numero">3</div>
                    <h4>Espera la aprobación</h4>
                    <p>El instituto revisa tu solicitud. Cuando la aprueba, ves en tu portal tu curso, horario, notas y estado de pagos.</p>
                </div>
            </div>
            <div class="acciones" style="justify-content:center; margin-bottom: 10px;">
                <a href="{{ route('alumno.inscripcion') }}" class="boton boton-principal">Iniciar mi inscripción</a>
            </div>
        </section>

        <footer class="pie">
            6.ª calle Poniente, colonia Romero, 3 cuadras arriba de Despensa Familiar, Aguilares, San Salvador<br>
            I.T.M. © {{ date('Y') }} — Instituto Técnico de Computación de Aguilares
        </footer>
    </div>
</body>
</html>

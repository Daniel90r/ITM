<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-institutional-favicon />
    <title>ITM Aguilares</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6366f1 0%, #38bdf8 50%, #7c3aed 100%);
            overflow: hidden;
            position: relative;
        }
        .blob {
            position: fixed;
            opacity: 0.20;
            pointer-events: none;
        }
        input:focus {
            outline: none;
            border-color: #f59e0b !important;
            background: rgba(177, 6, 6, 0.18) !important;
        }
        input::placeholder { color: rgba(255,255,255,0.32); }
    </style>
</head>
<body>

    {{-- Blobs decorativos --}}
    <div class="blob" style="width:180px;height:180px;background:#a5b4fc;top:40px;left:60px;border-radius:40% 60% 70% 30%;"></div>
    <div class="blob" style="width:140px;height:140px;background:#7dd3fc;top:60px;right:100px;border-radius:60% 40% 30% 70%;"></div>
    <div class="blob" style="width:200px;height:200px;background:#c4b5fd;bottom:30px;right:120px;border-radius:50% 50% 30% 70%;"></div>
    <div class="blob" style="width:130px;height:130px;background:#bae6fd;bottom:60px;left:140px;border-radius:30% 70% 60% 40%;"></div>
    <div class="blob" style="width:80px;height:80px;background:#ffffff;top:320px;left:30px;border-radius:50%;"></div>
    <div class="blob" style="width:70px;height:70px;background:#ddd6fe;top:350px;right:40px;border-radius:50%;"></div>

    {{-- Card glassmorphism --}}
    <div style="background:rgba(255,255,255,0.13); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.22); border-radius:18px; padding:40px 36px; width:340px; max-width:calc(100% - 32px); position:relative; z-index:2;">

        {{-- Logo --}}
        <div style="display:flex; flex-direction:column; align-items:center; gap:10px; margin-bottom:24px;">
            <div style="width:70px; height:70px; border-radius:50%; border:2px solid #f59e0b; background:rgba(255,255,255,0.18); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:58px; height:58px; object-fit:cover; border-radius:50%;">
            </div>
            <p style="color:white; font-size:14px; font-weight:600; text-align:center; line-height:1.5;">Instituto de Computación<br>de Aguilares</p>
            <p style="color:rgba(255,255,255,0.60); font-size:11px;">Sistema de Gestión Académica</p>
        </div>

        <h2 style="color:white; font-size:20px; font-weight:700; margin-bottom:20px;">Iniciar sesión</h2>

        {{-- Errores --}}
        @if ($errors->any())
        <div role="alert" style="display:flex; align-items:flex-start; gap:10px; background:#FFF1F2; color:#991B1B; border:1px solid #FCA5A5; border-radius:10px; padding:12px 14px; margin-bottom:16px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex-shrink:0; margin-top:1px;"><path d="M10.3 3.9 1.8 18.5A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.5L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/></svg>
            <div style="min-width:0; display:flex; flex-direction:column; gap:6px;">
                @foreach ($errors->all() as $error)
                <p style="font-size:14px; font-weight:500; line-height:1.5; overflow-wrap:anywhere; margin:0;">{{ $error }}</p>
                @endforeach
            </div>
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}" style="display:flex; flex-direction:column; gap:14px;">
            @csrf

            {{-- Email --}}
            <div>
                <label style="display:block; font-size:11px; font-weight:500; color:rgba(255,255,255,0.75); margin-bottom:6px;">Correo electrónico</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="correo@itm.edu.sv"
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid rgba(255,255,255,0.22); border-radius:9px; background:rgba(255,255,255,0.10); color:white; transition:all .15s;">
            </div>

            {{-- Contraseña --}}
            <div>
                <label style="display:block; font-size:11px; font-weight:500; color:rgba(255,255,255,0.75); margin-bottom:6px;">Contraseña</label>
                <input type="password" name="password" required placeholder="••••••••"
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid rgba(255,255,255,0.22); border-radius:9px; background:rgba(255,255,255,0.10); color:white; transition:all .15s;">
            </div>

            {{-- Recuérdame --}}
            <div style="display:flex; align-items:center; justify-content:flex-start;">
                <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:rgba(255,255,255,0.70); cursor:pointer;">
                    <input type="checkbox" name="remember" style="accent-color:#f59e0b; width:13px; height:13px;">
                    Recuérdame
                </label>
            </div>

            {{-- Botón --}}
            <button type="submit"
                style="width:100%; padding:11px; border-radius:9px; background:linear-gradient(90deg,#f59e0b,#fbbf24); color:#3b0764; font-size:14px; font-weight:700; border:none; cursor:pointer; transition:opacity .15s;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                Iniciar sesión
            </button>
        </form>

        <p style="font-size:11px; color:rgba(255,255,255,0.38); text-align:center; margin-top:24px;">
            I.T.M. © {{ date('Y') }} — Instituto de Computación de Aguilares
        </p>
    </div>

</body>
</html>

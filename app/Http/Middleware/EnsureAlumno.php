<?php

namespace App\Http\Middleware;

use App\Models\Alumno;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAlumno
{
    /**
     * El portal estudiantil exige un expediente de alumno vinculado al usuario.
     *
     * Un usuario con role = admin o docente no tiene (ni debe tener) un registro
     * en 'alumnos'. Antes esto devolvía un 403 crudo sin explicación; ahora se
     * redirige al portal propio del rol con un mensaje claro.
     *
     * La autorización NO se relaja: si no hay expediente, el portal estudiantil
     * nunca se renderiza.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        $tieneExpediente = Alumno::where('user_id', $user->id)->exists();

        if (!$tieneExpediente) {
            $destino = match ($user->role) {
                'admin' => route('admin.dashboard'),
                'docente' => route('docente.notas'),
                default => route('dashboard'),
            };

            return redirect($destino)->with(
                'error',
                'Tu usuario no tiene un expediente de alumno asociado, por lo que no puede entrar al portal estudiantil.'
            );
        }

        return $next($request);
    }
}

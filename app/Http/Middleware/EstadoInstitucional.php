<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AccesoInstitucional;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EstadoInstitucional
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || $user->role === 'admin' || $request->routeIs('logout')) {
            return $next($request);
        }
        // Let the existing role middleware reject cross-role URLs first.
        if ($request->is('alumno/*') && $user->role !== 'alumno') {
            return $next($request);
        }
        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:') && ! in_array($user->role, explode(',', substr($middleware, 5)), true)) {
                return $next($request);
            }
        }
        $valid = DB::transaction(function () use ($request, $user, $next) {
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            if (! $fresh || ! app(AccesoInstitucional::class)->permitido($fresh)) {
                return false;
            }
            $version = $request->session()->get('auth_version');
            // Only a validated current remember cookie may establish a new session.
            if ($version === null && Auth::guard('web')->viaRemember()) {
                $cookie = $request->cookie(Auth::guard('web')->getRecallerName());
                $parts = explode('|', (string) $cookie);
                if (count($parts) !== 3 || ! str_starts_with((string) $fresh->remember_token, 'itm-access-v1:') || ! hash_equals($fresh->remember_token, $parts[1])) {
                    return false;
                }
                $request->session()->regenerate();
                $request->session()->put('auth_version', (int) $fresh->auth_version);
                $version = $fresh->auth_version;
            }
            if ($version === null || (int) $version !== (int) $fresh->auth_version) {
                return false;
            }

            // Keep the account lock until the authenticated request finishes.
            return $next($request);
        });
        if ($valid === false) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesión no disponible. Inicia sesión nuevamente o consulta a administración.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'Sesión no disponible. Inicia sesión nuevamente o consulta a administración.']);
        }

        return $valid;
    }
}

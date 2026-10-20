<?php

namespace App\Http\Middleware;

use App\Services\ControlTransicionDocumental;
use Closure;
use Illuminate\Http\Request;

class ProtegerEscriturasDocumentales
{
    public function handle(Request $request, Closure $next): mixed
    {
        return app(ControlTransicionDocumental::class)->operacion(fn () => $next($request));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RespaldoBaseDatos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RespaldoController extends Controller
{
    public function index(RespaldoBaseDatos $respaldos)
    {
        return view('admin.respaldos', ['respaldos' => $respaldos->listar()]);
    }

    public function store(RespaldoBaseDatos $respaldos)
    {
        set_time_limit(300);

        try {
            $nombre = $respaldos->generar();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.respaldos')
                ->with('error', 'No se pudo generar la copia de seguridad. Inténtalo de nuevo o revisa el registro del sistema.');
        }

        Log::info('Copia de seguridad generada', ['archivo' => $nombre, 'usuario_id' => auth()->id()]);

        return redirect()->route('admin.respaldos')
            ->with('success', "Copia de seguridad generada: {$nombre}. Descárgala y guárdala fuera del servidor.");
    }

    public function descargar(RespaldoBaseDatos $respaldos, string $archivo)
    {
        abort_unless($respaldos->existe($archivo), 404);

        Log::info('Copia de seguridad descargada', ['archivo' => $archivo, 'usuario_id' => auth()->id()]);

        return Storage::disk('local')->download($respaldos->ruta($archivo), $archivo, [
            'Content-Type' => 'application/sql',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function destroy(RespaldoBaseDatos $respaldos, string $archivo)
    {
        abort_unless($respaldos->existe($archivo), 404);

        $respaldos->eliminar($archivo);
        Log::info('Copia de seguridad eliminada', ['archivo' => $archivo, 'usuario_id' => auth()->id()]);

        return redirect()->route('admin.respaldos')->with('success', "Se eliminó la copia {$archivo}.");
    }
}

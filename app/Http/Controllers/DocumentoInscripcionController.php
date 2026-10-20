<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Inscripcion;
use App\Models\DocumentoInscripcion;
use App\Services\DocumentosInscripcion;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentoInscripcionController extends Controller
{
    public function show(Request $request, Inscripcion $inscripcion, DocumentosInscripcion $documentos): BinaryFileResponse
    {
        $this->autorizar($request, $inscripcion);
        abort_unless($documentos->habilitado() && $inscripcion->documento_path, 404);
        if ($documentos->historialActivo()) {
            $this->exigirTabla();
            $version = $inscripcion->documentoVigente()->first();
            if ($version) {
                abort_unless($version->ruta === $inscripcion->documento_path, 404);
                return $this->entregarVersion($version, $documentos);
            }
        }
        try { $archivo = $documentos->localizar($inscripcion->documento_path); }
        catch (RuntimeException $e) { abort(404); }
        abort_unless($archivo, 404);
        return $this->descarga($archivo);
    }

    public function version(Request $request, Inscripcion $inscripcion, string $documento, DocumentosInscripcion $documentos): BinaryFileResponse
    {
        $this->autorizar($request, $inscripcion);
        abort_unless($documentos->historialActivo(), 404);
        $this->exigirTabla();
        $version = $inscripcion->documentos()->whereKey($documento)->firstOrFail();
        return $this->entregarVersion($version, $documentos);
    }

    public function historial(Request $request, Inscripcion $inscripcion, DocumentosInscripcion $documentos): \Illuminate\Http\JsonResponse
    {
        $this->autorizar($request, $inscripcion);
        abort_unless($documentos->historialActivo(), 404);
        $this->exigirTabla();
        $prefijo = $request->user()->role === 'admin' ? 'admin.solicitudes' : 'alumno.inscripciones';
        $historial = $inscripcion->documentos()->paginate(20);
        return response()->json([
            'documentos' => $historial->getCollection()->map(fn ($d) => [
                'id' => $d->id, 'version' => $d->version, 'vigente' => (bool) $d->vigente,
                'bytes' => $d->bytes, 'registrado_at' => $d->registrado_at->toIso8601String(),
                'descarga' => route($prefijo.'.documentos.version', [$inscripcion->id, $d->id]),
            ]), 'pagina' => $historial->currentPage(), 'paginas' => $historial->lastPage(), 'total' => $historial->total(),
        ])->header('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')->header('Expires', '0')->header('X-Content-Type-Options', 'nosniff');
    }

    private function exigirTabla(): void
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('documentos_inscripcion'), 404);
    }

    private function autorizar(Request $request, Inscripcion $inscripcion): void
    {
        $usuario = $request->user();
        abort_unless(in_array($usuario->role, ['admin', 'alumno'], true), 403);
        if ($usuario->role === 'alumno') {
            abort_unless(Alumno::where('id', $inscripcion->alumno_id)->where('user_id', $usuario->id)->exists(), 404);
        }
    }

    private function entregarVersion(DocumentoInscripcion $version, DocumentosInscripcion $documentos): BinaryFileResponse
    {
        try {
            $archivo = $documentos->localizarEnDisco($version->disco, $version->ruta);
        } catch (RuntimeException $e) {
            abort(404);
        }
        abort_unless($archivo, 404);
        abort_unless(hash_file('sha256', $archivo) === $version->sha256 && filesize($archivo) === $version->bytes, 404);
        return $this->descarga($archivo);
    }

    private function descarga(string $archivo): BinaryFileResponse
    {
        $response = response()->download($archivo, 'solicitud-inscripcion.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        // BinaryFileResponse marca las descargas como públicas por defecto.
        $response->setPrivate();
        $response->setEtag(null);
        $response->setLastModified(null);
        return $response;
    }
}

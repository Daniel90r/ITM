<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Director;
use App\Models\User;
use App\Services\DirectorService;
use App\Services\FirmaDirectorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DirectorController extends Controller
{
    public function index()
    {
        $directorActual = Director::activo()
            ->with("user")
            ->orderByDesc("fecha_inicio")
            ->first();

        $historial = Director::historicos()
            ->with("user")
            ->orderByDesc("fecha_inicio")
            ->paginate(10);

        $usersAdmin = User::where("role", "admin")
            ->select("id", "name", "email")
            ->orderBy("name")
            ->get();

        return view("admin.directores.index", compact(
            "directorActual",
            "historial",
            "usersAdmin"
        ));
    }

    public function store(Request $request, DirectorService $directorService)
    {
        $validated = $request->validate([
            "nombre" => ["required", "string", "max:100"],
            "dui" => ["nullable", "string", "max:10", "regex:/^\d{8}-\d$/"],
            "telefono" => ["nullable", "string", "max:9", "regex:/^\d{4}-\d{4}$/"],
            "email" => ["nullable", "email", "max:255"],
            "user_id" => ["nullable", "integer"],
            "fecha_inicio" => ["required", "date"],
        ]);

        $teniaDirectorAnterior = Director::activo()->exists();

        $firmaPath = null;

        try {
            $firmaPath = app(FirmaDirectorService::class)->guardar($request);

            $directorData = array_merge($validated, ['firma_path' => $firmaPath]);

            $directorService->nombrar($directorData);

            $mensaje = $teniaDirectorAnterior
                ? "Nuevo Director registrado. El período anterior fue cerrado correctamente."
                : "Director registrado correctamente.";

            return redirect()
                ->route("admin.directores.index")
                ->with("success", $mensaje);

        } catch (\Throwable $e) {
            if ($firmaPath && Storage::disk('local')->exists($firmaPath)) {
                Storage::disk('local')->delete($firmaPath);
            }
            throw $e;
        }
    }

    public function updateFirma(Request $request, DirectorService $directorService, Director $director)
    {
        if (!$director->activo) {
            throw ValidationException::withMessages(['firma' => 'Solo se puede actualizar la firma del Director vigente.'])->errorBag('firmaDirector');
        }
        $newPath = null;

        try {
            $newPath = app(FirmaDirectorService::class)->guardar($request, true);

            $director->update(['firma_path' => $newPath]);

            // Conservar el archivo anterior y las copias históricas de las boletas.

            return redirect()
                ->route("admin.directores.index")
                ->with("success", "Firma del Director actualizada correctamente.");

        } catch (\Throwable $e) {
            if ($newPath && Storage::disk('local')->exists($newPath)) {
                Storage::disk('local')->delete($newPath);
            }
            throw $e;
        }
    }

    public function showFirma(Director $director): Response
    {
        $path = $director->firma_path;

        if (! $path) {
            abort(404);
        }

        if (! str_starts_with($path, 'directores/firmas/')) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $absolutePath = Storage::disk('local')->path($path);
        $mimeType = Storage::disk('local')->mimeType($path);

        $response = response()->file($absolutePath, ['Content-Type' => $mimeType]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store', true);

        return $response;
    }
}

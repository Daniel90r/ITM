<?php

namespace App\Services;

use App\Models\Director;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DirectorService
{
    /**
     * Nombre una nueva vigencia de Director.
     *
     * Cierra (sin borrar) todos los períodos actualmente activos y crea uno
     * nuevo activo. La operación es atómica: bajo concurrencia real en MySQL
     * el lockForUpdate sobre los activos previene que dos transacciones
     * terminen con dos directores activos al mismo tiempo.
     */
    public function nombrar(array $datos): Director
    {
        $validados = Validator::make($datos, [
            "nombre"       => ["required", "string", "max:100"],
            "user_id"      => [
                "nullable",
                "integer",
                Rule::exists("users", "id")->where(
                    fn ($query) => $query->where("role", "admin")
                ),
            ],
            "dui"          => ["nullable", "string", "max:10", "regex:/^\d{8}-\d$/"],
            "telefono"     => ["nullable", "string", "max:9", "regex:/^\d{4}-\d{4}$/"],
            "email"        => ["nullable", "email", "max:255"],
            "firma_path"   => ["nullable", "string"],
            "fecha_inicio" => ["required", "date"],
        ])->validate();

        $nuevaFechaIni = Carbon::parse($validados["fecha_inicio"])->startOfDay();

        return DB::transaction(function () use ($validados, $nuevaFechaIni) {
            $activos = Director::where("activo", true)->lockForUpdate()->get();

            foreach ($activos as $director) {
                if ($nuevaFechaIni->lessThan($director->{"fecha_inicio"})) {
                    throw ValidationException::withMessages([
                        "fecha_inicio" => [
                            "El nuevo período no puede comenzar antes del inicio del Director activo.",
                        ],
                    ]);
                }
            }

            $cierre = $nuevaFechaIni->copy()->subDay();

            foreach ($activos as $director) {
                if ($cierre->lessThan($director->{"fecha_inicio"})) {
                    throw ValidationException::withMessages([
                        "fecha_inicio" => [
                            "La fecha de cierre resultante ("
                            . $cierre->toDateString()
                            . ") es anterior al inicio del Director ("
                            . $director->{"fecha_inicio"} . ")."
                            . " No se inventa una fecha inválida.",
                        ],
                    ]);
                }

                $director->activo = false;
                $director->{"fecha_fin"} = $cierre;
                $director->save();
            }

            return Director::create([
                "user_id"      => $validados["user_id"] ?? null,
                "nombre"       => $validados["nombre"],
                "dui"          => $validados["dui"] ?? null,
                "telefono"     => $validados["telefono"] ?? null,
                "email"        => $validados["email"] ?? null,
                "firma_path"   => $validados["firma_path"] ?? null,
                "fecha_inicio" => $nuevaFechaIni->toDateString(),
                "fecha_fin"    => null,
                "activo"       => true,
            ]);
        });
    }
}

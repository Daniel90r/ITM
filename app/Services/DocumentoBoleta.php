<?php

namespace App\Services;

use App\Exceptions\BoletaStorageException;
use App\Models\{Alumno, Boleta, Curso, DetalleCurso, Materia, Nota};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Solo snapshots y archivos privados: no consulta notas ni catálogos actuales. */
class DocumentoBoleta
{
    public function ruta(Boleta $boleta): string
    {
        if (!$boleta->id || !ctype_digit((string) $boleta->id)) throw new \LogicException('Emisión sin identificador válido.');
        return $boleta->id.'/oficial.pdf';
    }

    public function descargar(Boleta $boleta)
    {
        $disco = Storage::disk('boletas_oficiales');
        $ruta = $this->ruta($boleta);
        // Nunca regenerar ni sustituir un original existente.
        if ($disco->exists($ruta)) return $disco->download($ruta, 'boleta-emision-'.$boleta->id.'.pdf', ['Content-Type'=>'application/pdf']);
        return Pdf::loadView('admin.pdf.boleta-historica', $this->datosSnapshots($boleta))
            ->setPaper('a4', 'portrait')->download('boleta-reconstruida-'.$boleta->id.'.pdf');
    }

    public function datosSnapshots(Boleta $boleta, bool $nueva = false, ?string $estadoAcademico = null, ?string $docenteUltimaMateria = null): array
    {
        $boleta->loadMissing('detalles');
        $requeridos = ['alumno_nombre','alumno_apellido','alumno_codigo','curso_nombre','promedio_general','concepto_general'];
        foreach ($requeridos as $campo) {
            if ($boleta->$campo === null || $boleta->$campo === '') $this->incompleta();
        }
        if (!$boleta->fecha_emision || $boleta->detalles->isEmpty()) $this->incompleta();
        $alumno = new Alumno(['nombre'=>$boleta->alumno_nombre,'apellido'=>$boleta->alumno_apellido,'codigo'=>$boleta->alumno_codigo]);
        $curso = new Curso(['nombre'=>$boleta->curso_nombre,'nivel'=>$boleta->curso_turno,'seccion'=>$boleta->curso_seccion,'anio_lectivo'=>$boleta->curso_anio_lectivo]);
        $notas = $boleta->detalles->map(function ($detalle) use ($boleta) {
            if (!filled($detalle->materia_nombre)) $this->incompleta();
            foreach (['laboratorio','examen_teorico','practica','promedio'] as $campo) {
                if ($detalle->$campo === null || !is_numeric($detalle->$campo)) $this->incompleta();
            }
            $dc = new DetalleCurso(['curso_id'=>$boleta->curso_id,'materia_id'=>$detalle->materia_id]);
            $dc->setRelation('materia', new Materia(['nombre'=>$detalle->materia_nombre,'codigo'=>$detalle->materia_codigo]));
            $nota = new Nota(['detalle_curso_id'=>$detalle->detalle_curso_id,'promedio'=>$detalle->promedio,'conducta'=>$detalle->conducta]);
            $nota->setRelation('detalleCurso', $dc);
            return $nota;
        });
        $datos = [
            'alumno'=>$alumno,'curso'=>$curso,'notas'=>$notas,'promedio_general'=>$boleta->promedio_general,
            'boletaEmitida'=>$boleta,'fechaBoleta'=>$boleta->fecha_emision,'firmaDirector'=>$this->firma($boleta),
            'calificacionesCompletas'=>true,'reconstruccionHistorica'=>!$nueva,
        ];
        if ($nueva && $estadoAcademico !== null) {
            $datos['estado_academico'] = $estadoAcademico;
            $datos['docente_ultima_materia'] = $docenteUltimaMateria;
        }
        if (!$nueva) {
            // No hay aspectos ni conceptos consolidados persistidos: no inventarlos.
            $datos['bloquesHistoricos'] = $notas->map(fn ($nota) => [
                'aspecto'=>'No registrado en la emisión', 'concepto'=>'No registrado en la emisión',
                'notas'=>collect([$nota]), 'referencia'=>false,
            ])->all();
        }
        return $datos;
    }

    public function firma(Boleta $boleta): ?string
    {
        $ruta = $boleta->director_firma_snapshot_path;
        if (!$ruta) return null;
        if (!str_starts_with($ruta, 'boletas/firmas/'.$boleta->id.'/') || str_contains($ruta, '..') || str_contains($ruta, '\\')) {
            throw ValidationException::withMessages(['emision'=>'La ruta de la firma histórica no es válida.']);
        }
        if (!Storage::disk('local')->exists($ruta)) return null;
        $bytes = Storage::disk('local')->get($ruta);
        $imagen = @getimagesizefromstring($bytes);
        if (!$imagen || !in_array($imagen[2], [IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['emision'=>'La firma histórica no es una imagen válida.']);
        }
        return 'data:'.$imagen['mime'].';base64,'.base64_encode($bytes);
    }

    /** Crear una vez, con exclusión a nivel de filesystem. No usar put(), que sobrescribe. */
    public function conservar(Boleta $boleta, ?string $estadoAcademico = null, ?string $docenteUltimaMateria = null): string
    {
        try {
            return $this->crearArchivo($boleta, $estadoAcademico, $docenteUltimaMateria);
        } catch (\League\Flysystem\FilesystemException $e) {
            throw new BoletaStorageException('archivo_oficial', 'Fallo del almacenamiento privado.', $e);
        }
    }

    private function crearArchivo(Boleta $boleta, ?string $estadoAcademico = null, ?string $docenteUltimaMateria = null): string
    {
        $ruta = $this->ruta($boleta);
        $disco = Storage::disk('boletas_oficiales');
        // Impedir guardar sobre otro backend con garantías diferentes.
        if (config('filesystems.disks.boletas_oficiales.driver') !== 'local') throw new \RuntimeException('El archivo oficial requiere almacenamiento local privado.');
        if ($disco->exists($ruta)) throw new BoletaStorageException('colision_emision', 'No se sobrescribirán archivos oficiales existentes.');
        $bytes = Pdf::loadView('admin.pdf.boleta', $this->datosSnapshots($boleta, true, $estadoAcademico, $docenteUltimaMateria))->setPaper('a4','portrait')->output();
        if (!$disco->makeDirectory((string) $boleta->id)) {
            throw new BoletaStorageException('crear_directorio', 'No se pudo preparar el directorio oficial.');
        }
        $archivo = @fopen($disco->path($ruta), 'x+b');
        if (!$archivo) throw new BoletaStorageException('crear_exclusivo', 'No se puede crear el PDF oficial sin sobrescribir un archivo existente.');
        try {
            $posicion = 0;
            while ($posicion < strlen($bytes)) {
                $escritos = $this->escribir($archivo, substr($bytes, $posicion));
                if (!$escritos) throw new BoletaStorageException('escribir_pdf', 'No se pudo completar el PDF oficial.');
                $posicion += $escritos;
            }
            if (!$this->sincronizar($archivo)) throw new BoletaStorageException('sincronizar_pdf', 'No se pudo sincronizar el PDF oficial.');
        } catch (\Throwable $e) {
            fclose($archivo);
            try {
                // Solo el archivo creado exclusivamente en este intento fallido.
                if (!$disco->delete($ruta)) $this->registrarFalloLimpieza($boleta);
            } catch (\League\Flysystem\FilesystemException) {
                $this->registrarFalloLimpieza($boleta);
            }
            throw $e;
        }
        fclose($archivo);
        return $ruta;
    }

    /** Separar las operaciones de stream permite reproducir fallos parciales sin tocar archivos reales. */
    protected function escribir($archivo, string $bytes): int|false
    {
        return @fwrite($archivo, $bytes);
    }

    protected function sincronizar($archivo): bool
    {
        return @fflush($archivo) && (!function_exists('fsync') || @fsync($archivo));
    }

    private function registrarFalloLimpieza(Boleta $boleta): void
    {
        \Illuminate\Support\Facades\Log::error('boleta.limpieza_archivo_fallida', ['boleta_id' => $boleta->id]);
    }

    private function incompleta(): never
    {
        throw ValidationException::withMessages(['emision'=>'Los snapshots de esta emisión no contienen información académica suficiente. No se utilizarán datos actuales para completarlos.']);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoInscripcion extends Model
{
    protected $table = 'documentos_inscripcion';
    public $timestamps = false;
    protected $fillable = ['inscripcion_id', 'version', 'ruta', 'disco', 'sha256', 'bytes', 'registrado_at', 'vigente'];
    protected $casts = ['version' => 'integer', 'bytes' => 'integer', 'registrado_at' => 'datetime', 'vigente' => 'boolean'];

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Materia extends Model
{
    use HasFactory;
    protected $fillable = ['nombre', 'codigo', 'descripcion', 'activa'];

    public function detalleCursos(): HasMany
    {
        return $this->hasMany(DetalleCurso::class);
    }
}

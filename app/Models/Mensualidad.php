<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mensualidad extends Model
{
    use HasFactory;

    protected $table = 'mensualidades'; // ← esto le dice el nombre real

    protected $fillable = [
        'alumno_id',
        'curso_id',
        'mes',
        'anio',
        'monto',
        'estado',
        'fecha_vencimiento',
    ];

    protected $appends = ['nombre_mes'];

    protected static array $meses = [
        '00' => 'Sin mes',
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
        '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
        '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
        '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
    ];

    public function getNombreMesAttribute(): string
    {
        $mes = $this->mes;
        if ($mes === null || $mes === '') {
            return 'Sin mes';
        }
        if (is_numeric($mes)) {
            $mes = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);

            return self::$meses[$mes] ?? $mes;
        }

        return $mes;
    }

    public function alumno()
    {
        return $this->belongsTo(Alumno::class);
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }
}

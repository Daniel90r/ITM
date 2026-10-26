<?php

// Muestras privadas con objetos en memoria. No migra, guarda modelos ni emite boletas.
require dirname(__DIR__).'/estado-personas-bootstrap.php';
require_once dirname(__DIR__).'/Feature/BoletaEstructuraConductaTest.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Se requiere SQLite en memoria.');
}
Illuminate\Support\Facades\DB::listen(static function () { throw new RuntimeException('No se permiten consultas.'); });

$salida = sys_get_temp_dir().'/itm-boleta-estructura-'.date('Ymd-His');
mkdir($salida, 0700, true);
$base = collect(App\Support\BoletaConductaPresentacion::REFERENCIA)->flatten()->all();
$casos = [
    'produccion-mediana-b-mb' => ['Microsoft Windows','Microsoft Word'],
    'produccion-mediana-mb-e' => ['Microsoft Windows','Microsoft Word'],
    'produccion-mediana-mb-p' => ['Microsoft Windows','Microsoft Word'],
    'produccion-mediana-mb-pendiente' => ['Microsoft Windows','Microsoft Word'],
    'produccion-dos-materias' => ['Microsoft Windows', 'Microsoft Word'],
    'produccion-con-ingles' => ['Microsoft Windows', 'Microsoft Word', 'Inglés'],
    'produccion-sin-notas' => ['Microsoft Windows', 'Microsoft Word'],
    'produccion-conductas' => ['Microsoft Windows', 'Microsoft Word', 'Microsoft Excel', 'Internet', 'Inglés'],
    'produccion-sin-conducta' => ['Microsoft Windows', 'Microsoft Word'],
    'produccion-sin-correspondencia' => ['Contabilidad', 'Inglés'],
    'produccion-multipagina' => array_map(fn ($i) => 'Materia sintética '.$i.' con nombre extenso para comprobar legibilidad', range(1, 48)),
    'referencia-13' => $base,
    'con-ingles' => [...$base, 'Inglés'],
    'tres-iguales' => $base,
    'tres-diferentes' => $base,
    'cuatro-diferentes' => $base,
    'sin-conducta' => $base,
    'otro-programa' => ['Contabilidad', 'Inglés'],
    'sin-notas' => [],
    'multipagina' => [...$base, ...array_map(fn ($i) => 'Materia adicional sintética '.$i.' con nombre extenso para comprobar legibilidad', range(1, 35))],
];
$resultados = [];
foreach ($casos as $nombre => $materias) {
    if (in_array('--historica', $argv, true)) continue;
    if (in_array('--consolidacion', $argv, true) && !str_starts_with($nombre,'produccion-mediana-') && !in_array($nombre,['referencia-13','con-ingles','produccion-sin-correspondencia','produccion-multipagina'],true)) continue;
    $seleccion = array_values(array_filter($argv, fn ($arg) => str_starts_with($arg, '--caso=')));
    if ($seleccion && $nombre !== substr($seleccion[0], 7)) continue;
    if (in_array('--produccion', $argv, true) && !str_starts_with($nombre, 'produccion-') && $nombre !== 'referencia-13') continue;
    $notas = Tests\Feature\BoletaEstructuraConductaTest::notasSinteticas($materias);
    if ($nombre === 'tres-iguales') foreach ($notas->take(3) as $nota) $nota->conducta = 'E';
    if ($nombre === 'tres-diferentes') foreach (['E', 'MB', 'B'] as $i => $codigo) $notas[$i]->conducta = $codigo;
    if ($nombre === 'cuatro-diferentes') foreach (['E', 'MB', 'B', 'P'] as $i => $codigo) $notas[$i + 9]->conducta = $codigo;
    if ($nombre === 'sin-conducta') foreach ($notas as $nota) $nota->conducta = null;
    $produccion = str_starts_with($nombre, 'produccion-');
    if ($produccion) {
        foreach ($notas as $i => $nota) {
            $maestro = new App\Models\Maestro(['nombre'=>'Docente sintético '.($i % 2 + 1), 'apellido'=>'PRUEBA']);
            $asignacion = new App\Models\AsignacionDocente(['estado'=>'activa', 'fecha_inicio'=>now()->subMonth()->toDateString()]);
            $asignacion->setRelation('maestro', $maestro);
            $nota->detalleCurso->setRelation('asignacionesDocentes', collect([$asignacion]));
            $nota->conducta = ['B', 'MB', 'E', 'P', null][$i % 5];
            $nota->laboratorio = $nota->examen_teorico = $nota->practica = 8;
        }
    }
    if ($nombre === 'produccion-sin-notas') foreach ($notas as $nota) {
        $nota->promedio = null;
        $nota->conducta = null;
    }
    if ($nombre === 'produccion-dos-materias') {
        $notas[0]->conducta = 'MB';
        $notas[1]->conducta = 'P';
    }
    if ($nombre === 'produccion-sin-conducta') foreach ($notas as $nota) $nota->conducta = null;
    $pares = ['produccion-mediana-b-mb'=>['B','MB'],'produccion-mediana-mb-e'=>['MB','E'],'produccion-mediana-mb-p'=>['MB','P'],'produccion-mediana-mb-pendiente'=>['MB',null]];
    if (isset($pares[$nombre])) foreach ($pares[$nombre] as $i=>$codigo) $notas[$i]->conducta=$codigo;
    if (in_array('--consolidacion',$argv,true) && in_array($nombre,['referencia-13','con-ingles'],true)) foreach ($notas as $i=>$nota) $nota->conducta=['B','MB','E'][$i%3];
    $datos = Tests\Feature\BoletaEstructuraConductaTest::datos($produccion ? $notas : $notas->reverse()->values());
    $datos['promedio_general'] = $notas->whereNotNull('promedio')->avg('promedio');
    $datos['calificacionesCompletas'] = $notas->isNotEmpty() && $notas->every(fn ($nota) => $nota->promedio !== null);
    if ($produccion) $datos['curso']->nombre = 'Curso sintético (sin programa oficial confirmado)';
    $html = view($produccion ? 'admin.pdf.boleta' : 'admin.pdf.boleta-prueba', $datos)->render();
    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait')->setOption('isRemoteEnabled', false);
    file_put_contents($salida.'/'.$nombre.'.html', $html);
    file_put_contents($salida.'/'.$nombre.'.pdf', $pdf->output());
    $resultados[$nombre] = ['materias' => $notas->count(), 'paginas' => $pdf->getDomPDF()->getCanvas()->get_page_count()];
}
if (in_array('--historica', $argv, true)) {
    $boleta = new App\Models\Boleta([
        'alumno_nombre'=>'Alumno histórico sintético','alumno_apellido'=>'SIN VALIDEZ','alumno_codigo'=>'HIST-PRUEBA',
        'curso_nombre'=>'Curso histórico de prueba','curso_turno'=>'Matutino','curso_seccion'=>'A','curso_anio_lectivo'=>now()->subYears(2)->year,
        'curso_id'=>999,'director_nombre'=>'Directora histórica sintética','fecha_emision'=>now()->subYears(2),
        'promedio_general'=>8,'concepto_general'=>'MB',
    ]);
    $boleta->id=777;
    $detalles=collect(['Microsoft Windows','Microsoft Word'])->map(fn ($nombre,$i) => new App\Models\DetalleBoleta([
        'detalle_curso_id'=>$i+1,'materia_id'=>$i+1,'materia_nombre'=>$nombre,'materia_codigo'=>'HIST'.$i,
        'laboratorio'=>8,'examen_teorico'=>8,'practica'=>8,'promedio'=>8,'conducta'=>$i===0?'MB':'P',
    ]));
    $boleta->setRelation('detalles',$detalles);
    $html=view('admin.pdf.boleta-historica', app(App\Services\DocumentoBoleta::class)->datosSnapshots($boleta))->render();
    $pdf=Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4','portrait')->setOption('isRemoteEnabled',false);
    file_put_contents($salida.'/historica-reconstruida.html',$html);
    file_put_contents($salida.'/historica-reconstruida.pdf',$pdf->output());
    $resultados['historica-reconstruida']=['materias'=>2,'paginas'=>$pdf->getDomPDF()->getCanvas()->get_page_count()];
}
echo json_encode(['directorio' => $salida, 'sin_consultas_ni_migraciones' => true, 'casos' => $resultados], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

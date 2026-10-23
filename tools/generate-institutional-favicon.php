<?php

// Ejecutar manualmente desde la raíz: php tools/generate-institutional-favicon.php
// Conserva el logotipo completo y su proporción; no requiere Laravel ni la BD.
$root = dirname(__DIR__);
$source = imagecreatefromjpeg($root.'/public/images/logo_itm.jpg');
if (!$source) {
    throw new RuntimeException('No se pudo leer el logotipo institucional.');
}
$directory = $root.'/public/favicons';
if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
    throw new RuntimeException('No se pudo crear el directorio de iconos.');
}
$images = [];
foreach ([16, 32, 48, 180, 192] as $size) {
    $canvas = imagecreatetruecolor($size, $size);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    $scale = min($size / imagesx($source), $size / imagesy($source));
    $width = (int) round(imagesx($source) * $scale);
    $height = (int) round(imagesy($source) * $scale);
    imagecopyresampled($canvas, $source, (int) floor(($size - $width) / 2), (int) floor(($size - $height) / 2), 0, 0, $width, $height, imagesx($source), imagesy($source));
    ob_start();
    imagepng($canvas);
    $images[$size] = ob_get_clean();
    if (in_array($size, [32, 180, 192], true)) {
        file_put_contents($directory.'/itm-'.$size.'.png', $images[$size]);
    }
}
// ICO multirresolución con imágenes PNG, admitido por navegadores modernos.
$header = pack('vvv', 0, 1, 3);
$offset = 6 + 16 * 3;
$entries = $payload = '';
foreach ([16, 32, 48] as $size) {
    $length = strlen($images[$size]);
    $entries .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, $length, $offset);
    $payload .= $images[$size];
    $offset += $length;
}
file_put_contents($root.'/public/favicon.ico', $header.$entries.$payload);
echo "Iconos institucionales generados desde public/images/logo_itm.jpg.\n";

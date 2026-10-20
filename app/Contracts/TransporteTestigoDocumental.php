<?php

namespace App\Contracts;

/** Transporte autenticado futuro. Nunca reintentar una mutación automáticamente.
 * Debe imponer el plazo total (incluye conexión/lectura), limitar la respuesta
 * a 32768 bytes y autenticar al ejecutor fuera del cuerpo del mensaje.
 * No se entrega implementación de red ni credenciales en esta fase.
 */
interface TransporteTestigoDocumental
{
    public function intercambiar(string $operacion, array $mensaje, int $plazoMilisegundos): array;
}

<?php

namespace App\Contracts;

/**
 * Frontera externa de confianza. Debe conservar consumos de forma duradera,
 * no permitir rollback y serializar consumir() por (registro, época, ID).
 * Cada respuesta Ed25519 cubre la solicitud completa y su desafío fresco.
 * Una caché, archivo local o una mera declaración de disponibilidad no bastan.
 * Registro/época deben estar provisionados fuera del cliente: un identificador
 * desconocido o una continuidad no verificable NO se interpreta como libre.
 * El adaptador real debe autenticar al ejecutor y demostrar integridad global;
 * no se entrega tal adaptador ni se infieren garantías de su mera firma.
 */
interface RegistroConsumosIndependiente
{
    public function exigirDisponibleParaCanalReal(): void;

    /** Consulta linealizable, sin consumir; devuelve {payload, firma} Base64. */
    public function consultar(array $solicitud): array;

    /** CAS irreversible libre->consumida. Un perdedor DEBE fallar, nunca dar éxito idempotente. */
    public function consumir(array $solicitud): array;
}

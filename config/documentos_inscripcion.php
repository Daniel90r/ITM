<?php

return [
    // Habilitar únicamente después de aprobar el traslado.
    'enabled' => env('INSCRIPCION_DOCUMENTOS_PRIVADOS', false),
    'legacy_read_enabled' => env('INSCRIPCION_DOCUMENTOS_LEGACY_READ', false),
    'history_enabled' => env('INSCRIPCION_DOCUMENTOS_HISTORIAL', false),
    // Preparado para una ventana futura. No habilitar sin revisar todos los escritores.
    'transition_guard_enabled' => false,
    // Raíz alternativa solo tras revisión de ACL/publicación HTTP; no activa la barrera.
    'transition_control_root' => env('INSCRIPCION_TRANSICION_CONTROL_ROOT'),
    // Precondiciones, nunca interruptores de habilitación. Sin valores no hay canal real.
    'authorization_root' => env('INSCRIPCION_AUTORIDAD_ROOT'),
    'permits_root' => env('INSCRIPCION_PERMISOS_ROOT'),
    'consumption_root' => env('INSCRIPCION_CONSUMOS_ROOT'),
    'quarantine_root' => env('INSCRIPCION_CUARENTENA_ROOT'),
    'executor_sid' => env('INSCRIPCION_EJECUTOR_SID'),
    // Inventario completo aprobado de publicaciones y huellas de .itm-raiz.
    // Provisionar mediante configuración privada revisada, no descubrir por confianza inicial.
    'http_roots' => [],
    'root_identity_sha256' => [],
    // Clave PÚBLICA del testigo independiente, distinta de la del firmante de permisos.
    'consumption_registry' => ['id' => null, 'epoch' => null, 'public_key' => null],
];

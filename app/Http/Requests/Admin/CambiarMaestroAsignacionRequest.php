<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarMaestroAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'nuevo_maestro_id' => [
                'required',
                'integer',
                Rule::exists('maestros', 'id')->where('activo', true),
            ],
            'motivo_cambio' => [
                'required',
                'string',
                'min:10',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nuevo_maestro_id.required' => 'El maestro es obligatorio.',
            'nuevo_maestro_id.integer' => 'El maestro debe ser un número entero.',
            'nuevo_maestro_id.exists' => 'El maestro seleccionado no existe o está inactivo.',
            'motivo_cambio.required' => 'El motivo del cambio es obligatorio.',
            'motivo_cambio.string' => 'El motivo debe ser texto.',
            'motivo_cambio.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo_cambio.max' => 'El motivo no puede tener más de 255 caracteres.',
        ];
    }
}

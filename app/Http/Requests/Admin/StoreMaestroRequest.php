<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaestroRequest extends FormRequest
{
    protected $errorBag = 'storeMaestro';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'size:9', 'regex:/^[267][0-9]{3}-[0-9]{4}$/'],
            'dui' => ['required', 'string', 'size:10', 'regex:/^[0-9]{8}-[0-9]$/', 'unique:maestros,dui'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser texto.',
            'nombre.max' => 'El nombre no puede tener más de 255 caracteres.',
            'apellido.required' => 'El apellido es obligatorio.',
            'apellido.string' => 'El apellido debe ser texto.',
            'apellido.max' => 'El apellido no puede tener más de 255 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.string' => 'El teléfono debe ser texto.',
            'telefono.regex' => 'El teléfono debe tener el formato salvadoreño ####-#### y comenzar con 2, 6 ó 7.',
            'telefono.size' => 'El teléfono debe tener 9 caracteres con el formato ####-####.',
            'dui.required' => 'El DUI es obligatorio.',
            'dui.string' => 'El DUI debe ser texto.',
            'dui.size' => 'El DUI debe tener el formato ########-#',
            'dui.regex' => 'El DUI debe tener el formato ########-#',
            'dui.unique' => 'El DUI ya está registrado para otro maestro.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.max' => 'El correo electrónico no puede tener más de 255 caracteres.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}

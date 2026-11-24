<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgregarParadaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ciudad' => ['required', 'string', 'min:2', 'max:80'],
            'orden' => ['required', 'integer', 'min:1'],
            'diasEstancia' => ['required', 'integer', 'between:0,7'],
            'estado' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['estado.prohibited' => 'Toda parada nace PLANIFICADA'];
    }
}

<?php

namespace App\Http\Requests;

use App\Domain\Ofrenda;
use App\Domain\Rey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validación de FORMATO (400). Unicidad y estado se validan en CaravanaService. */
class GuardarCaravanaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rey' => ['required', 'string', Rule::in(Rey::valores())],
            'ofrenda' => ['required', 'string', Rule::in(Ofrenda::valores())],
            'origen' => ['required', 'string', 'min:2', 'max:80'],
            'camellos' => ['required', 'integer', 'between:1,12'],
            'estado' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['estado.prohibited' => 'El estado no se envía aquí: nace DETENIDA y cambia con PATCH /api/caravanas/{id}/estado'];
    }
}

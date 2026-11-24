<?php

namespace App\Http\Requests;

use App\Domain\EstadoCaravana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoCaravanaRequest extends FormRequest
{
    public function rules(): array
    {
        return ['estado' => ['required', 'string', Rule::in(EstadoCaravana::valores())]];
    }
}

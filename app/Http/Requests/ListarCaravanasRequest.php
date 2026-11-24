<?php

namespace App\Http\Requests;

use App\Domain\EstadoCaravana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarCaravanasRequest extends FormRequest
{
    public function rules(): array
    {
        return ['estado' => ['sometimes', 'string', Rule::in(EstadoCaravana::valores())]];
    }
}

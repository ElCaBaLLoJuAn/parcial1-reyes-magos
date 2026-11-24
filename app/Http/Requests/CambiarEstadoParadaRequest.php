<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoParadaRequest extends FormRequest
{
    public function rules(): array
    {
        return ['estado' => ['required', 'string', Rule::in(['CUMPLIDA', 'CANCELADA'])]];
    }
}

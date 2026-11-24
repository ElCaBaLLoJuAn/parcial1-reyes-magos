<?php

namespace App\Models;

use App\Domain\EstadoParada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parada extends Model
{
    protected $table = 'paradas';

    protected $fillable = ['caravana_id', 'ciudad', 'orden', 'dias_estancia', 'estado'];

    protected function casts(): array
    {
        return [
            'caravana_id' => 'integer',
            'orden' => 'integer',
            'dias_estancia' => 'integer',
            'estado' => EstadoParada::class,
        ];
    }

    public function caravana(): BelongsTo
    {
        return $this->belongsTo(Caravana::class);
    }
}

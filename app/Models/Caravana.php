<?php

namespace App\Models;

use App\Domain\EstadoCaravana;
use App\Domain\Ofrenda;
use App\Domain\Rey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caravana extends Model
{
    protected $table = 'caravanas';

    protected $fillable = ['rey', 'ofrenda', 'origen', 'camellos', 'estado', 'dias_restantes'];

    protected function casts(): array
    {
        return [
            'rey' => Rey::class,
            'ofrenda' => Ofrenda::class,
            'camellos' => 'integer',
            'estado' => EstadoCaravana::class,
            'dias_restantes' => 'integer',
        ];
    }

    public function paradas(): HasMany
    {
        return $this->hasMany(Parada::class)->orderBy('orden');
    }
}

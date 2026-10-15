<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivoHomeoTemp extends Model
{
    protected $table = 'activos_homeo_temp';

    protected $fillable = [
        'codigo', 'user_id', 'cod_activo', 'activo', 'dilusion',
    ];

    public function activoHomeo()
    {
        return $this->belongsTo(ActivoHomeo::class, 'cod_activo');
    }
}

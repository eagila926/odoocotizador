<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormulaHomeoItem extends Model
{
    protected $table = 'formulas_homeo_items';

    protected $fillable = [
        'codigo', 'cod_activo', 'activo', 'dilusion',
    ];

    public function formula()
    {
        return $this->belongsTo(FormulaHomeo::class, 'codigo', 'codigo');
    }
}

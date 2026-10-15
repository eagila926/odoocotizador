<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormulaHomeo extends Model
{
    protected $table = 'formulas_homeo';

    protected $fillable = [
        'codigo', 'nombre_etiqueta', 'categoria', 'user_id',
        'precio', 'medico', 'cedula_medico', 'presentacion',
    ];

    protected $casts = ['precio' => 'decimal:2'];

    public function items()
    {
        return $this->hasMany(FormulaHomeoItem::class, 'codigo', 'codigo');
    }
}

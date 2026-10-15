<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarritoFormulaItem extends Model
{
    protected $table = 'carrito_formulas_items';

    protected $fillable = [
        'user_id',
        'cod_formula',
        'nombre_formula',
        'categoria',
        'cantidad',
        'promocion',
        'observacion',
        'precio',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio'   => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relación con usuario (ajusta el namespace si tu modelo es distinto)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación con Formula por código
    public function formula()
    {
        return $this->belongsTo(Formula::class, 'cod_formula', 'codigo');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoFormulaItem extends Model
{
    protected $table = 'pedidos_formulas_items';

    protected $fillable = [
        'pedido_formula_id',
        'codigo',

        'cod_formula',
        'nombre_formula',
        'categoria',

        'cantidad',
        'detalle',
        'precio_unidad',
        'subtotal',

        'promocion',
    ];

    // Cada item pertenece a un pedido de fórmulas
    public function pedido()
    {
        return $this->belongsTo(PedidoFormula::class, 'pedido_formula_id');
    }

    // Si quieres relación con tabla formulas
    public function formula()
    {
        return $this->belongsTo(Formula::class, 'cod_formula', 'codigo');
    }
}

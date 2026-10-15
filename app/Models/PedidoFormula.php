<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoFormula extends Model
{
    protected $table = 'pedidos_formulas';

    protected $fillable = [
        'codigo',
        'fecha',
        'total',
        'user_id'
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    // Un pedido de fórmulas tiene muchos ítems
    public function items()
    {
        return $this->hasMany(PedidoFormulaItem::class, 'pedido_formula_id');
    }

    // Relación con usuario
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

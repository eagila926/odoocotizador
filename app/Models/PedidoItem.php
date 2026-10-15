<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoItem extends Model
{
    protected $table = 'pedidos_items';
    protected $fillable = [
        'pedido_id','codigo','cod_product','nombre','categoria',
        'cantidad','detalle','precio_unidad','subtotal','promocion'
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}

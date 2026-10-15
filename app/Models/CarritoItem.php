<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarritoItem extends Model
{
    protected $table = 'carrito_items';

    protected $fillable = [
        'user_id',
        'cod_product',
        'nombre',
        'categoria',
        'cantidad',
        'promocion',
        'observacion',
        'precio',
        'subtotal',
    ];
}

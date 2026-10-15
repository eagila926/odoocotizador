<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';
    protected $fillable = ['codigo','fecha','total','user_id'];

    public function items()
    {
        return $this->hasMany(PedidoItem::class, 'pedido_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

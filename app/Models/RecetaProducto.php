<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecetaProducto extends Model
{
    use HasFactory;

    protected $table = 'receta_productos';

    protected $fillable = [
        'id_receta',
        'cod_product',
        'nombre',
        'cantidad'
    ];

    public $timestamps = true;

    public function receta()
    {
        return $this->belongsTo(Receta::class, 'id_receta', 'id_receta');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'cod_product', 'cod_product');
    }
}

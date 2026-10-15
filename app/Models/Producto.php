<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'cod_product';
    public $incrementing = false;         
    protected $keyType = 'string';     
    public $timestamps = false;

    protected $fillable = [
        'cod_product', 'nombre', 'categoria', 'precio'
    ];
}

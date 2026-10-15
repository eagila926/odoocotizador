<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecetaHomeopatico extends Model
{
    use HasFactory;

    protected $table = 'receta_homeopaticos';

    protected $fillable = [
        'id_receta',
        'producto',
        'composicion',
        'cantidad_solicitada',
    ];

    public function receta()
    {
        return $this->belongsTo(Receta::class, 'id_receta', 'id_receta');
    }
}

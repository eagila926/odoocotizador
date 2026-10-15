<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    use HasFactory;

    protected $table = 'recetas';       // Nombre de la tabla
    protected $primaryKey = 'id_receta'; // Clave primaria

    public $timestamps = false; // No tienes campos created_at / updated_at

    protected $fillable = [
        'so',
        'codigo_formula',
        'fecha',
        'cedula_medico',
        'paciente',
        'num_frascos'
    ];

    protected static function booted()
    {
        static::creating(function (self $receta) {
            if (empty($receta->fecha)) {
                $receta->fecha = now()->subDays(2)->toDateString();
            }
        });
    }

    // Relación con productos
    public function productos()
    {
        return $this->hasMany(RecetaProducto::class, 'id_receta', 'id_receta');
    }

    public function homeopatico()
    {
        return $this->hasOne(RecetaHomeopatico::class, 'id_receta', 'id_receta');
    }

    // Relación con médico
    public function medico()
    {
        return $this->belongsTo(Medico::class, 'cedula_medico', 'cedula');
    }
}

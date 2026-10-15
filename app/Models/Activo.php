<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class Activo extends Model
{
    protected $table = 'activos';
    protected $primaryKey = 'cod_odoo';
    public $incrementing = false; // PK manual
    protected $keyType = 'int';
    public $timestamps = false; // según tu tabla


    protected $fillable = [
        'cod_odoo','nombre','valor_costo','factor','minimo',
        'maximo','unidad','factor_venta','densidad'
        ];

    public function getRouteKeyName()
    {
        return 'cod_odoo';
    }

    public function getMinimoNumberAttribute(): ?float
    {
        return is_numeric($this->minimo)
            ? (float) $this->minimo
            : null;
    }

    public function getMaximoNumberAttribute(): ?float
    {
        return is_numeric($this->maximo)
            ? (float) $this->maximo
            : null;
    }

}
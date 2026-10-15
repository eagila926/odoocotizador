<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medico extends Model
{
    use HasFactory;

    protected $table = 'medicos';

    protected $primaryKey = 'cedula';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'cedula',
        'full_name',
        'centro_medico',
        'correo',
        'direccion',
        'telefono',
        'user_id',
        'firma',
    ];

    protected $casts = [
        'firma' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id', 'id_user');
    }
}
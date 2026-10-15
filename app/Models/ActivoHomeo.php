<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivoHomeo extends Model
{
    protected $table = 'activos_homeo';
    public $timestamps = false;

    protected $fillable = ['nombre', 'categoria'];
}

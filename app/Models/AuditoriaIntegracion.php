<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditoriaIntegracion extends Model
{
    use HasFactory;
    protected $table = 'auditorias_integraciones';

    protected $fillable = [
        'uniqueid',
        'metodo',
        'request',
        'response',
        'ips',
        'user_agent',
        'funcion'
    ];
}

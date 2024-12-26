<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoChat extends Model
{
    use HasFactory;
    protected $table = 'pagos_chat';

    protected $fillable = [
        'api_key',
        'id_factura',
        'estado',
        'id_bancard',
        'resultado_bancard',
        'ci_ruc',
        'monto',
    ];
}

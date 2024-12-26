<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteToken extends Model {
    use HasFactory;
    protected $table = 'clientes_token';

    protected $fillable = [
        'token',
        'cliente_id',
        'fecha_expiracion',
    ];

    public function cliente() {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'clientecodigo');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model {
    use HasFactory;
    protected $table = 'clientes';

    public function sucursal_actual() {
        return $this->belongsTo('App\Models\Sucursal', 'sucursal', 'sucursal');
    }



    public function cliente_token() {
        return $this->hasOne(ClienteToken::class, 'cliente_id', 'clientecodigo');
    }
}

<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model {
    protected $table = 'clientes';
    public $timestamps = false;

    protected $fillable = [
        'cedula',
        'nombre_completo',
        'telefono',
        'correo',
        'direccion',
        'latitud',
        'longitud'
    ];

    protected $casts = [
        'id' => 'integer',
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function ventas() {
        return $this->hasMany(Venta::class, 'cliente_id');
    }
}

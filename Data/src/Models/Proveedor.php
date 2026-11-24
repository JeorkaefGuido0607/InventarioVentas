<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model {
    protected $table = 'proveedores';
    public $timestamps = false;

    protected $fillable = [
        'cedula_juridica',
        'nombre',
        'telefono',
        'correo',
        'direccion',
        'activo'
    ];

    protected $casts = [
        'id' => 'integer',
        'activo' => 'boolean',
    ];

    public function productos() {
        return $this->hasMany(Producto::class, 'proveedor_id');
    }
}

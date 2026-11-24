<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model {
    protected $table = 'ventas';
    public $timestamps = false;

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'fecha',
        'metodo_pago',
        'subtotal',
        'descuento',
        'impuesto',
        'total',
        'estado',
        'observaciones'
    ];

    protected $casts = [
        'id' => 'integer',
        'cliente_id' => 'integer',
        'usuario_id' => 'integer',
        'subtotal' => 'float',
        'descuento' => 'float',
        'impuesto' => 'float',
        'total' => 'float',
    ];

    public function detalle() {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }

    public function cliente() {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario() {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

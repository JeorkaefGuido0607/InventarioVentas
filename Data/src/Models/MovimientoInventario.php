<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model {
    protected $table = 'movimientos_inventario';
    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'usuario_id',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'motivo',
        'venta_id',
        'fecha'
    ];

    protected $casts = [
        'id' => 'integer',
        'producto_id' => 'integer',
        'usuario_id' => 'integer',
        'cantidad' => 'integer',
        'stock_anterior' => 'integer',
        'stock_nuevo' => 'integer',
        'venta_id' => 'integer',
    ];

    public function producto() {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}

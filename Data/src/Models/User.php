<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model {
    protected $table = 'usuarios';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'rol_id',
        'nombre_completo',
        'correo',
        'activo'
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'id' => 'integer',
        'rol_id' => 'integer',
        'activo' => 'boolean',
    ];

    public function rol() {
        return $this->belongsTo(Rol::class, 'rol_id');
    }
}

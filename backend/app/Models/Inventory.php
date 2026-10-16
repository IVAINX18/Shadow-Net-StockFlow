<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Represento el saldo materializado por par producto-almacén: existe por
// rendimiento para no sumar el kardex en cada consulta, y es la fila que la
// Unidad 2 bloqueará para serializar las confirmaciones. La Unidad 1 solo
// deja el modelo listo; ningún endpoint lo escribe todavía. El plural por
// defecto (`inventories`) ya coincide con el esquema documentado.
class Inventory extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'average_cost',
        'last_movement_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'average_cost' => 'decimal:6',
            'last_movement_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

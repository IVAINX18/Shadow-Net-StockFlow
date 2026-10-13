<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductWarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Represento la configuración de stock de un producto en un almacén (mínimo,
// máximo y punto de reorden): es la fila que el §7.7 expone y edita por
// almacén autorizado.
class ProductWarehouse extends Model
{
    /** @use HasFactory<ProductWarehouseFactory> */
    use HasFactory;

    // Declaro la tabla explícita porque el plural por defecto de Eloquent
    // sería `product_warehouses` y el esquema documentado usa el singular
    // `product_warehouse` (§5.2), igual que ocurrió con `units_of_measure`.
    protected $table = 'product_warehouse';

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'min_stock',
        'max_stock',
        'reorder_point',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Los decimales viajan como cadena en JSON, nunca como flotante
            // (§3 de api-design.md): así conservan la precisión DECIMAL(15,3).
            'min_stock' => 'decimal:3',
            'max_stock' => 'decimal:3',
            'reorder_point' => 'decimal:3',
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

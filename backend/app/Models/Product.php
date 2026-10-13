<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Represento el producto del catálogo: no declaro tabla explícita porque el
// plural por defecto de Eloquent (`products`) ya coincide con el esquema
// documentado, a diferencia de `units_of_measure` que sí la necesitó.
// Aplico SoftDeletes porque el §4 exige preservar historial y valorización.
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'sku',
        'name',
        'description',
        'barcode',
        'category_id',
        'unit_of_measure_id',
        'is_active',
        'cost_price',
        'sale_price',
        'costing_method',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            // Declaro los decimales como `decimal` para que viajen como cadena
            // en JSON y nunca como flotante (§3 de api-design.md): Eloquent
            // los serializa como string con la precisión indicada.
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /** @return HasMany<ProductWarehouse, $this> */
    public function configurations(): HasMany
    {
        return $this->hasMany(ProductWarehouse::class);
    }

    /** @return BelongsToMany<Warehouse, $this> */
    public function warehouses(): BelongsToMany
    {
        // Nombro la tabla y las claves explícitas porque el pivote documentado
        // es `product_warehouse` (singular) y el plural por defecto de
        // Eloquent (`product_warehouse_product` / `product_warehouses`) no
        // coincidiría con el esquema del §5.2.
        return $this->belongsToMany(Warehouse::class, 'product_warehouse', 'product_id', 'warehouse_id')
            ->withPivot(['id', 'min_stock', 'max_stock', 'reorder_point'])
            ->withTimestamps();
    }
}

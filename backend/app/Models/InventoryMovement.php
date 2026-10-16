<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Represento el efecto atómico sobre la existencia de un producto en un
// almacén: es append-only y la Unidad 1 no lo escribe, solo deja el modelo
// listo para que la Unidad 2 confirme contra él. Desactivo updated_at porque
// la tabla solo lleva created_at: un movimiento jamás se modifica (C-09).
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'document_id',
        'document_line_id',
        'product_id',
        'warehouse_id',
        'direction',
        'quantity_delta',
        'unit_cost',
        'balance_after',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'unit_cost' => 'decimal:6',
            'balance_after' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InventoryDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'document_id');
    }

    /** @return BelongsTo<InventoryDocumentLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(InventoryDocumentLine::class, 'document_line_id');
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

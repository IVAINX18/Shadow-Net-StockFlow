<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InventoryDocumentLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Represento el detalle de una operación: cada línea referencia un producto
// con cantidad siempre positiva (la dirección la fija el tipo de documento,
// RN-INV-01) y su total calculado en el servicio, nunca enviado por el
// cliente. El plural por defecto ya coincide con `inventory_document_lines`.
class InventoryDocumentLine extends Model
{
    /** @use HasFactory<InventoryDocumentLineFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'document_id',
        'product_id',
        'quantity',
        'direction',
        'unit_cost',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Los decimales viajan como cadena en JSON, nunca como flotante
            // (§3 de api-design.md): así conservan la precisión DECIMAL.
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:6',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<InventoryDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'document_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

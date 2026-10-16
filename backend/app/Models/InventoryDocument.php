<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InventoryDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Represento la intención de negocio del inventario: en esta unidad el
// documento solo nace y vive en `borrador`, sin tocar existencias. No declaro
// tabla explícita porque el plural por defecto (`inventory_documents`) ya
// coincide con el esquema documentado. La ruta usa el uuid público, nunca el
// id interno (§1 de database-design.md).
class InventoryDocument extends Model
{
    /** @use HasFactory<InventoryDocumentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'doc_number',
        'type',
        'status',
        'origin_warehouse_id',
        'destination_warehouse_id',
        'idempotency_key',
        'reference',
        'notes',
        'reason_code',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'reversed_by_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    // Expongo el uuid en las URLs para que el id interno nunca viaje como
    // identificador de documento, como ordena el §1 del modelo físico.
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return HasMany<InventoryDocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryDocumentLine::class, 'document_id');
    }

    // Expongo los movimientos del kardex generados al confirmar: el libro es
    // append-only y cada movimiento referencia su documento (I-05).
    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'document_id');
    }

    // Enlazo el documento de reversión que compensó a este original: la
    // corrección queda trazada sin editar ni borrar nada (RN-INV-07).
    /** @return BelongsTo<InventoryDocument, $this> */
    public function reversion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

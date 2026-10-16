<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Domain\Enums\InventoryDocumentStatus;
use App\Domain\Enums\InventoryDocumentType;
use Illuminate\Validation\Rule;

// Reúno aquí las reglas de forma del documento porque la creación manual y
// cualquier puerta futura (carga inicial de la Fase 3, importación) deben
// aplicar exactamente las mismas (RN-IMP-02): si vivieran copiadas en cada
// Form Request, divergirían igual que temía CatalogRules con el catálogo. Los
// Form Request delegan en mí y el servicio de dominio verifica el fondo
// (almacenes activos, coherencia por tipo, productos activos), así que el
// cambio de una regla ocurre en un solo sitio.
final class InventoryRules
{
    /**
     * Reglas de creación del borrador: forma del §7.8 sin reglas de fondo
     * (existencia de activos, destino obligatorio solo en transferencias y
     * motivo obligatorio solo en ajustes las verifica el servicio, porque
     * dependen de valores cruzados y no de campos aislados).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function documentRules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(InventoryDocumentType::values())],
            'origin_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
            'reason_code' => ['sometimes', 'nullable', 'string', Rule::in([
                'inventario_fisico', 'merma', 'robo', 'vencimiento', 'error_registro', 'donacion', 'otro',
            ])],
            'reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'notes' => ['sometimes', 'nullable', 'string'],
            // Reservo la bandera de confirmación inmediata (RF-075): la Unidad
            // 2 la consumirá por el mismo servicio transaccional sin cambiar
            // el contrato. Hoy solo se acepta en falso.
            'confirm' => ['sometimes', 'boolean'],
            // Cada línea trae producto, cantidad positiva como cadena y costo
            // como cadena: nunca flotantes (§3 de api-design.md). La dirección
            // por línea solo existe en el ajuste (el signo vive por línea,
            // §4.3 del dominio); el servicio rechaza su uso en otros tipos.
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'string', 'decimal:0,3'],
            'lines.*.unit_cost' => ['sometimes', 'string', 'decimal:0,6'],
            'lines.*.direction' => ['sometimes', 'nullable', 'string', Rule::in(['entrada', 'salida'])],
        ];
    }

    /**
     * Reglas de edición del borrador: idénticas a las de creación porque el
     * PUT repone el borrador completo (cabecera y líneas), igual que el alta.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function documentUpdateRules(): array
    {
        return self::documentRules();
    }

    /**
     * Reglas del listado: solo los filtros y órdenes documentados en el §7.8;
     * lo no documentado se ignora por no tener regla y el `sort` ajeno cae en
     * 422, igual que el listado de productos.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function documentIndexRules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(InventoryDocumentType::values())],
            'status' => ['sometimes', 'string', Rule::in(InventoryDocumentStatus::values())],
            'origin_warehouse_id' => ['sometimes', 'integer'],
            'destination_warehouse_id' => ['sometimes', 'integer'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'sort' => ['sometimes', 'string', Rule::in([
                'created_at', '-created_at',
                'doc_number', '-doc_number',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Reglas del kardex (§7.9): producto y almacén obligatorios; rango
     * ISO-8601 opcional y paginación. El orden es fijo (created_at, id) y por
     * eso no acepto `sort`: el kardex cronológico no se reordena.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function kardexRules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Reglas del reporte de existencias (§7.11): los tres filtros del contrato
     * más la paginación. Lo no documentado se ignora por no tener regla.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function stockReportRules(): array
    {
        return [
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            // Acepto las formas en que un booleano llega por query string: la
            // regla `boolean` de Laravel rechaza la cadena "true", y el tablero
            // anuncia el endpoint con `?below_min=true`, así que la incluyo
            // explícitamente junto a 1/0 y sus variantes.
            'below_min' => ['sometimes', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Mensajes en español compartidos por la captura manual y las puertas
     * futuras, para que el mismo fallo suene igual en todos los caminos.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'type.required' => 'El tipo de documento es obligatorio.',
            'type.in' => 'El tipo de documento no es válido.',
            'origin_warehouse_id.required' => 'El almacén de origen es obligatorio.',
            'origin_warehouse_id.exists' => 'El almacén de origen no es válido.',
            'destination_warehouse_id.exists' => 'El almacén de destino no es válido.',
            'reason_code.in' => 'El motivo de ajuste no es válido.',
            'reference.max' => 'La referencia no puede superar los 120 caracteres.',
            'confirm.boolean' => 'La confirmación inmediata no es válida.',
            'lines.required' => 'El documento debe traer al menos una línea.',
            'lines.min' => 'El documento debe traer al menos una línea.',
            'lines.max' => 'El documento no puede traer más de 500 líneas.',
            'lines.*.product_id.required' => 'Cada línea debe indicar su producto.',
            'lines.*.product_id.exists' => 'El producto de la línea no es válido.',
            'lines.*.quantity.required' => 'Cada línea debe indicar su cantidad.',
            'lines.*.quantity.string' => 'La cantidad debe enviarse como cadena decimal.',
            'lines.*.quantity.decimal' => 'La cantidad no es válida.',
            'lines.*.unit_cost.string' => 'El costo debe enviarse como cadena decimal.',
            'lines.*.unit_cost.decimal' => 'El costo no es válido.',
            'lines.*.direction.in' => 'La dirección de la línea no es válida.',
            'product_id.required' => 'El producto es obligatorio.',
            'product_id.exists' => 'El producto no es válido.',
            // Sin este mensaje el campo caía al texto por defecto de Laravel, que
            // está en inglés: la interfaz lo mostraba tal cual al usuario.
            'warehouse_id.required' => 'El almacén es obligatorio.',
            'warehouse_id.exists' => 'El almacén no es válido.',
            'category_id.exists' => 'La categoría no es válida.',
            'from.date' => 'La fecha inicial no es válida.',
            'to.date' => 'La fecha final no es válida.',
            'below_min.in' => 'El filtro de bajo mínimo no es válido.',
        ];
    }
}

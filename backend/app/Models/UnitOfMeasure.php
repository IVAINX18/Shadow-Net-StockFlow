<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Represento la unidad de medida del catálogo: no expongo relación hacia
// productos porque ningún endpoint del contrato la recorre; la FK vive en
// `products.unit_of_measure_id` con RESTRICT como red dura.
class UnitOfMeasure extends Model
{
    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasFactory;

    // Declaro la tabla explícita porque el plural por defecto de Eloquent sería
    // `unit_of_measures` y el esquema documentado usa `units_of_measure` (§5.2).
    protected $table = 'units_of_measure';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'allows_decimals',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allows_decimals' => 'boolean',
        ];
    }
}

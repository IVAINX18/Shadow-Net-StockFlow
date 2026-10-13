<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Represento la categoría del catálogo como nodo de un árbol con profundidad
// acotada a 3 niveles: la guarda vive en el servicio, aquí solo expongo la
// navegación padre/hijas para que el servicio y el Resource la recorran.
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Expongo los productos de la categoría porque el detalle del contrato
    // exige el conteo y la desactivación debe rechazarla con productos
    // activos (Unidad 2 de la Fase 2): la relación vive aquí y la guarda en
    // el servicio, sin cambiar el contrato del árbol.
    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Reúno todos los descendientes en PHP porque el árbol está acotado a 3
    // niveles: con tan poca profundidad una recursión corta es más legible que
    // un CTE y basta para la comprobación de ciclos del servicio.
    /**
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        $this->loadMissing('children');

        foreach ($this->children as $child) {
            $ids[] = (int) $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    // Calculo la profundidad contando ancestros (raíz = 1) porque el servicio
    // la necesita para imponer el tope de 3 niveles antes de asignar un padre.
    public function depth(): int
    {
        $depth = 1;
        $parent = $this->parent()->first();

        while ($parent !== null) {
            $depth++;
            $parent = $parent->parent()->first();
        }

        return $depth;
    }
}

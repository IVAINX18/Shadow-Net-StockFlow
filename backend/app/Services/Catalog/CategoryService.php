<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

// Concentro aquí las reglas del árbol (sin ciclos, tope de 3 niveles y guarda
// de desactivación) porque son reglas de dominio que deben aplicarse igual
// desde cualquier entrada: el Form Request valida la forma y yo el fondo.
final class CategoryService
{
    // Profundidad máxima del árbol: raíz, hija y nieta. [DECISIÓN de la Unidad 1]
    public const MAX_DEPTH = 3;

    // Verifico el padre antes de crearlo o moverlo porque un ciclo dejaría el
    // árbol inconsistente y ningún CHECK de MySQL puede expresarlo (riesgo de
    // la Fase 2 en scope-and-milestones.md).
    /**
     * @throws ValidationException
     */
    public function assertValidParent(?Category $category, mixed $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = Category::query()->find((int) $parentId);

        // El Form Request ya exige que el padre exista; si no lo encuentro aquí
        // es una carrera y dejo que la FK con RESTRICT decida en la base.
        if ($parent === null) {
            return;
        }

        if ($category !== null && (int) $parent->id === (int) $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => ['Una categoría no puede ser su propio padre.'],
            ]);
        }

        if ($category !== null && in_array((int) $parent->id, $category->descendantIds(), true)) {
            throw ValidationException::withMessages([
                'parent_id' => ['El padre no puede ser una subcategoría de la propia categoría.'],
            ]);
        }

        // El nuevo nodo colgaría un nivel por debajo del padre, así que el
        // padre debe estar como máximo en el nivel MAX_DEPTH - 1.
        if ($parent->depth() >= self::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => ['La jerarquía admite como máximo 3 niveles.'],
            ]);
        }

        // Mover una rama entera también puede desbordar el tope por abajo:
        // compruebo que la rama completa quepa bajo el nuevo padre.
        if ($category !== null) {
            $alturaRama = $this->subtreeHeight($category);
            if ($parent->depth() + $alturaRama > self::MAX_DEPTH) {
                throw ValidationException::withMessages([
                    'parent_id' => ['La jerarquía admite como máximo 3 niveles.'],
                ]);
            }
        }
    }

    // Expongo si la rama tiene hijas activas para que el controlador responda
    // 409 ESTADO_INVALIDO (RF-030): la comprobación de productos vive en
    // ProductService porque esa tabla pertenece a la Unidad 2.
    public function hasActiveChildren(Category $category): bool
    {
        return $category->children()->where('is_active', true)->exists();
    }

    // Resuelvo la categoría por nombre para la importación de catálogo (EP-012):
    // si no existe la creo como raíz, porque el archivo solo trae el nombre y
    // no la jerarquía. No valido actividad aquí: el llamante la verifica con
    // ProductService igual que la captura manual (RN-IMP-02).
    public function resolveOrCreateRootByName(string $name): Category
    {
        $nombre = trim($name);

        $existente = Category::query()
            ->where('name', $nombre)
            ->orderBy('id')
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        return Category::create([
            'name' => $nombre,
            'parent_id' => null,
        ]);
    }

    // Mido la altura de la rama (1 = hoja) recorriendo hijas en PHP porque el
    // árbol está acotado: es la pieza que falta para validar el traslado de
    // ramas completas contra el tope de profundidad.
    private function subtreeHeight(Category $category): int
    {
        $category->loadMissing('children');

        $altura = 1;

        foreach ($category->children as $child) {
            $altura = max($altura, 1 + $this->subtreeHeight($child));
        }

        return $altura;
    }
}

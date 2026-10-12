<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    // Aplico SoftDeletes porque el §4 me exige preservar el kardex del
    // almacén: desactivar no basta si alguien lo borra por error.
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'address',
        'city',
        'manager_user_id',
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

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        // Nombro la tabla explícita porque la migración la creó como
        // warehouse_user y el nombre por defecto no coincidiría.
        return $this->belongsToMany(User::class, 'warehouse_user');
    }

    // Filtro los almacenes operativos porque casi todas las consultas del
    // dominio deben ignorar desactivados y borrados sin repetir el where.
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

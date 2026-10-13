<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Http\Request;

// Registro la bitácora de catálogo desde un único punto porque así garantizo
// que cada alta, edición y desactivación deje su entrada con responsable,
// acción, entidad y valores, y que ningún secreto se cuele en los JSON (C-10).
final class AuditLogger
{
    // Claves que jamás persisto en old/new_values, aunque el llamante las
    // incluya por descuido: la auditoría no guarda secretos (RF-113).
    /** @var list<string> */
    private const SECRET_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'remember_token',
        'api_key',
        'secret',
    ];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $this->truncateUserAgent((string) $request->userAgent()),
        ]);
    }

    // Elimino las claves secretas de ambos lados del cambio porque el llamante
    // me entrega el modelo serializado y yo no puedo confiar en que ya venga
    // limpio: la última red de C-10 vive aquí.
    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::SECRET_KEYS as $secret) {
            unset($values[$secret]);
        }

        return $values;
    }

    // Trunco el agente a 255 caracteres porque la columna no admite más y un
    // User-Agent manipulado no puede romper la inserción con un 500.
    private function truncateUserAgent(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 255);
    }
}

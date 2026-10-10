<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

// Lo mantengo invokable y sin lógica de negocio porque en Fase 0 solo
// necesito probar que la base /api/v1 responde sin depender de MySQL.
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Devuelvo la hora en UTC con ISO-8601 para que el chequeo sea comparable entre entornos.
        return response()->json([
            'status' => 'ok',
            'service' => 'shadow-net-stockflow-api',
            'version' => 'v1',
            'timestamp' => now()->utc()->toIso8601String(),
        ]);
    }
}

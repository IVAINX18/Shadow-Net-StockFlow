<?php

// Verifico solo el contrato del endpoint (200 y status=ok) sin base de datos,
// porque en Fase 0 MySQL aún no corre y el health debe funcionar igual.
test('el endpoint de salud responde ok', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk();
    $response->assertJsonPath('status', 'ok');
    $response->assertJsonPath('service', 'shadow-net-stockflow-api');
    $response->assertJsonPath('version', 'v1');
});

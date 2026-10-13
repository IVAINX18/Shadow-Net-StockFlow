import { defineConfig } from 'vitest/config';

// Configuracion de pruebas para un equipo de memoria ajustada (riesgo R-10) y un
// repositorio en disco montado: un unico proceso hijo dedicado y sin paralelismo
// entre archivos, en lugar del pool de varios workers que agota la memoria al
// arrancar. Uso el pool de procesos (forks) y no el de hilos: en este equipo el
// de hilos no responde dentro del tiempo de arranque y la ejecucion falla. Uso la
// extension .mts para que Vite cargue el archivo como modulo ESM.
export default defineConfig({
  test: {
    pool: 'forks',
    singleFork: true,
    isolate: false,
    fileParallelism: false,
    maxWorkers: 1,
    minWorkers: 1,
    // La primera compilacion en este disco puede tardar; doy margen para que el
    // trabajador arranque sin agotar el tiempo de espera por defecto.
    testTimeout: 30_000,
    hookTimeout: 30_000,
    teardownTimeout: 30_000,
  },
});

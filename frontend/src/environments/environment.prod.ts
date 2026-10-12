// Entorno de produccion. La URL publica de la API se fija en el despliegue; el
// valor queda como marcador con dominio reservado (.example) a la espera de la
// fase de operacion. angular.json lo aplica con fileReplacements.
export const environment = {
  production: true,
  apiBaseUrl: 'https://api.stockflow.example',
};

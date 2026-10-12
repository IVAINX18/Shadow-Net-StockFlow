// Entorno de preproduccion. El host real de ensayo se asignara al desplegar;
// uso el dominio reservado .example (RFC 2606) para no inventar infraestructura
// que todavia no existe. angular.json sustituye environment.ts por este archivo
// con fileReplacements en la configuracion "staging".
export const environment = {
  production: false,
  apiBaseUrl: 'https://staging.stockflow.example',
};

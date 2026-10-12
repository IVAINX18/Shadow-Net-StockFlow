// Entorno de desarrollo: la SPA habla con la API local que expone el
// contenedor de Podman en el puerto 8000. El nombre del archivo y la clave
// apiBaseUrl son los que fija docs/architecture/frontend-architecture.md §9.
export const environment = {
  production: false,
  apiBaseUrl: 'http://localhost:8000',
};

// Tipado de los archivos de entorno (frontend-architecture.md §9). El codigo de
// la aplicacion nunca lee variables sueltas: inyecta API_BASE_URL y el tipo
// asegura que los tres entornos mantengan la misma forma.
export interface AppEnvironment {
  readonly production: boolean;
  readonly apiBaseUrl: string;
}

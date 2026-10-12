# Arquitectura del frontend - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento describe la arquitectura de la SPA Angular del ERP Shadow-Net StockFlow: componentes, estado, formularios, servicios, interceptores, rutas y convenciones. La visión del sistema está en [system-architecture.md](./system-architecture.md), las decisiones de versión en [architecture-decisions.md](./architecture-decisions.md) (ADR-006 y ADR-014) y la API que consume en [backend-architecture.md](./backend-architecture.md). El dominio cuyas reglas solo aplica el backend está en [inventory-domain.md](./inventory-domain.md).

## 1. Principios

- Componentes standalone como unidad de construcción; sin módulos de funcionalidad salvo el enrutador raíz. [DECISIÓN]
- Zoneless por defecto (comportamiento de Angular 21 y superiores): el cambio se propaga por Signals sin zona de detección global. [DECISIÓN]
- Signals para el estado local y `computed()` para los derivados; RxJS solo donde el flujo asíncrono es natural (peticiones HTTP, combinaciones de observables, cancelaciones). [DECISIÓN]
- Formularios reactivos tipados para operaciones de inventario; validación de presentación que nunca sustituye la validación del backend.
- Servicios como única puerta de acceso a la API; componentes que no llaman a `HttpClient` directamente.
- Interceptores funcionales para autenticación y errores centralizados; guardias de ruta como ayuda de navegación, con la autorización real siempre en el backend.

Versiones aplicables (ver [architecture-decisions.md](./architecture-decisions.md)): Angular 22.2.x, TypeScript 6.0.x, RxJS 7.4.x y `@angular/material` 22.2.x, con Node 22 LTS o 24 LTS (pendiente de confirmación del entorno según ADR-006).

## 2. Estructura de carpetas

```text
frontend/
  src/
    app/
      core/                  Servicios transversales e infraestructura.
        services/
          api.service.ts     Cliente base (apiBaseUrl, cabeceras, idempotencia)
          auth.service.ts    Sesión, perfil, cierre de sesión
          error.service.ts   Normalización de errores tipados
        interceptors/
          auth.interceptor.ts
          error.interceptor.ts
        guards/
          auth.guard.ts      Ayuda de navegación (no autoriza)
          role.guard.ts      Ayuda de navegación por rol (no autoriza)
        models/              Tipos estrictos (Document, Movement, Warehouse, ...)
      shared/                Componentes, directivas y tuberías reutilizables.
        components/
        pipes/
      features/              Una carpeta por capacidad de negocio.
        auth/
        warehouses/
        products/
        inventory/           Documentos, confirmación, kardex, existencias
        dashboard/
        reports/
      app.routes.ts          Definición de rutas con carga perezosa
      app.config.ts          provideHttpClient(withInterceptors(...)), zoneless, Material
    environments/
      environment.ts         Desarrollo (apiBaseUrl local)
      environment.staging.ts
      environment.prod.ts
```

Cada funcionalidad (`features/*`) contiene sus componentes standalone, sus servicios de acceso a datos y sus rutas hijas con carga perezosa; `core` no contiene presentación y `shared` no contiene lógica de negocio.

## 3. Componentes standalone y zoneless

[DECISIÓN] Cada vista y cada pieza reutilizable es un componente standalone con importaciones explícitas; el enrutador carga cada funcionalidad de forma perezosa para reducir el paquete inicial, consideración relevante con memoria limitada en el equipo local.

[DECISIÓN] La aplicación opera en modo zoneless: los componentes exponen estado con `signal()`, derivan con `computed()` y mutan con `set()` o `update()`; no se depende de la detección global por zona. Esto reduce trabajo innecesario de detección de cambios y hace explícito qué estado provoca cada actualización.

Esqueleto ilustrativo de un componente de lista de existencias (sin código completo):

```ts
// features/inventory/stock-list.component.ts (esqueleto)
@Component({ standalone: true, imports: [MatTableModule], templateUrl: './stock-list.component.html' })
export class StockListComponent {
  private readonly documents = inject(InventoryService);
  readonly stocks = signal<StockRow[]>([]);
  readonly total = computed(() => this.stocks().reduce((acc, r) => acc + r.quantity, 0));
}
```

## 4. Estado con Signals y uso acotado de RxJS

- Estado local (formularios en edición, selección de almacén, paginación, resultados cargados): Signals.
- Valores derivados (totales, saldos calculados para mostrar, banderas de stock bajo): `computed()`.
- RxJS solo donde el flujo asíncrono lo pide: la petición HTTP devuelve observable, se combina con operadores (`switchMap` para búsquedas, `catchError` delegado al interceptor, `takeUntilDestroyed` para cancelación al destruir el componente). [DECISIÓN]
- Se descarta una librería de estado global (NgRx) por sobredimensionada para el MVP; cada servicio de funcionalidad conserva sus Signals y los componentes consumen solo lectura. [DECISIÓN]

## 5. Formularios reactivos

[DECISIÓN] Las operaciones de inventario (crear documento, añadir líneas, confirmar, revertir) usan Reactive Forms tipados: cada línea es un grupo con controles de producto, cantidad y costo; el formulario valida formatos y campos requeridos para una buena experiencia, pero el backend revalida todo (cantidades, disponibilidad, motivo de ajuste, almacenes distintos). Los errores 422 del backend se mapean a errores por control para mostrarlos junto al campo.

## 6. Servicios de acceso a la API

Los servicios de `core` y de cada funcionalidad son la única capa que conoce la URL base y el contrato OpenAPI:

- `ApiService` centraliza `apiBaseUrl` (tomado de `environments/*`), las cabeceras JSON y la generación o reutilización de la clave de idempotencia por documento.
- Cada servicio de funcionalidad (`InventoryService`, `WarehouseService`, `ProductService`, `ReportService`) expone métodos tipados (`createDocument`, `confirmDocument`, `listMovements`) que devuelven observables de modelos estrictos.
- Los componentes nunca importan `HttpClient`; inyectan el servicio correspondiente y actualizan sus Signals con el resultado.

Esqueleto ilustrativo:

```ts
// core/services/api.service.ts (esqueleto)
@Injectable({ providedIn: 'root' })
export class InventoryService {
  private readonly http = inject(HttpClient);
  private readonly base = inject(API_BASE_URL); // apiBaseUrl del environment activo
  confirmDocument(uuid: string, key: string) {
    return this.http.post<Document>(`${this.base}/api/v1/inventory-documents/${uuid}/confirm`, { idempotency_key: key });
  }
}
```

## 7. Interceptores funcionales y errores centralizados

[DECISIÓN] La configuración usa `provideHttpClient(withInterceptors([...]))` con interceptores funcionales:

- `auth.interceptor`: incluye las credenciales de sesión y el manejo CSRF de Sanctum; ante un 401 redirige al inicio de sesión conservando la ruta deseada.
- `error.interceptor`: normaliza los errores del backend a un tipo `AppError` (validación por campo, negocio con detalle de producto, autorización, servidor con identificador de correlación) para que los componentes muestren mensajes accionables sin lógica dispersa.

Esqueleto ilustrativo del registro:

```ts
// app.config.ts (esqueleto)
export const appConfig: ApplicationConfig = {
  providers: [
    provideHttpClient(withInterceptors([authInterceptor, errorInterceptor])),
    // enrutador, Material y API_BASE_URL según el environment activo
  ],
};
```

## 8. Rutas y guardias como ayuda de navegación

[DECISIÓN] Las guardias (`auth.guard`, `role.guard`) mejoran la navegación (ocultan enlaces, redirigen a inicio de sesión o a una página de acceso denegado), pero la autorización real vive en las Policies y Gates del backend. Un usuario sin acceso a un almacén recibe 403 del servidor aunque manipule la URL; las pruebas de autorización del backend lo verifican.

Las rutas de funcionalidad se cargan de forma perezosa desde `app.routes.ts`; el panel, los almacenes, los productos, el inventario y los reportes son rutas hijas con su guardia de navegación correspondiente.

## 9. Entornos y configuración

| Fichero | Uso |
| --- | --- |
| `environments/environment.ts` | Desarrollo local: `apiBaseUrl` hacia el backend local o del contenedor. |
| `environments/environment.staging.ts` | Preproducción: URL de la API de ensayo. |
| `environments/environment.prod.ts` | Producción: URL pública de la API. |

[DECISIÓN] La URL base nunca se fija en el código de los servicios; siempre proviene del environment activo. Los secretos (si el frontend necesitara alguno en el futuro) se inyectan en tiempo de construcción o despliegue, nunca se confirman en el repositorio.

## 10. Interfaz con Material y tipado estricto

- La identidad visual (tokens, temas claro y oscuro, tipografía, color, forma, densidad, estados, copy y lista de verificación) está en [design-system.md](./design-system.md), que es la fuente única de esas decisiones.
- `@angular/material` 22.2.x para tablas (kardex, existencias), formularios, diálogos de confirmación y reversión, y navegación accesible.
- `strict` activado en TypeScript: modelos sin `any` implícito, formularios tipados, respuestas HTTP con tipos generados o mantenidos desde el contrato OpenAPI.
- Código y nombres técnicos en inglés; textos visibles y documentación en español.

## 11. Referencias

- Sistema: [system-architecture.md](./system-architecture.md).
- Decisiones: [architecture-decisions.md](./architecture-decisions.md).
- Backend: [backend-architecture.md](./backend-architecture.md).
- Despliegue: [deployment-architecture.md](./deployment-architecture.md).
- Dominio: [inventory-domain.md](./inventory-domain.md).
- Proyecto: [../planning/project-charter.md](../planning/project-charter.md).

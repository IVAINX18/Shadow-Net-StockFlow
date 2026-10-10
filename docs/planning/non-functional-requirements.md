# Requisitos no funcionales - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

## 1. Propósito y base técnica

Este archivo fija los atributos de calidad del ERP Shadow-Net StockFlow: cómo debe comportarse el sistema más allá de sus funciones. Los requisitos funcionales viven en [functional-requirements.md](./functional-requirements.md), el índice maestro en [requirements.md](./requirements.md) y los criterios verificables en [acceptance-criteria.md](./acceptance-criteria.md). Las decisiones de plataforma citadas provienen de [../architecture/architecture-decisions.md](../architecture/architecture-decisions.md) y el acta de alcance de [project-charter.md](./project-charter.md).

Pila fijada para el MVP: Laravel 13.35 sobre PHP 8.4, MySQL 8.4 LTS con InnoDB, Angular 22.2 con Node 22 o 24 LTS y TypeScript 6.0. Cada requisito indica su categoría, su descripción verificable y su fase.

## 2. Rendimiento

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-001 | Las consultas de existencias y kardex paginado responden dentro de tiempos aptos para operación interactiva con índices definidos en el diseño de datos. | MVP |
| RNF-002 | La confirmación de un documento con hasta 50 líneas completa su transacción sin degradar la operación concurrente de otros almacenes. [ESTIMACIÓN] Fuente: volumen operativo esperado del MVP descrito en el acta. | MVP |
| RNF-003 | Los listados usan paginación e índices; ninguna pantalla exige cargar un catálogo completo en memoria. | MVP |
| RNF-004 | Los reportes que superan el umbral de volumen se ejecutan en cola de base de datos sin bloquear la sesión del usuario. | MVP |

## 3. Seguridad

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-005 | Toda autorización se aplica en el servidor con el sistema de roles y permisos; la interfaz nunca sustituye esa verificación. | MVP |
| RNF-006 | El alcance por almacén se verifica en el servidor en cada consulta y documento, no solo al iniciar sesión. | MVP |
| RNF-007 | Las contraseñas se almacenan solo como hash; jamás en texto plano ni en registros o auditoría. | MVP |
| RNF-008 | La bitácora excluye contraseñas, tokens y secretos en todos sus campos. | MVP |
| RNF-009 | Los intentos de autenticación aplican limitación de frecuencia para frenar ataques de fuerza bruta. | MVP |
| RNF-010 | Las operaciones de inventario aplican limitación de frecuencia por usuario para contener reintentos abusivos sin bloquear la operación legítima. | MVP |
| RNF-011 | La sesión de la aplicación propia usa el mecanismo de sesión de la plataforma con protección contra falsificación de peticiones; los tokens de integración futura llevan habilidades restringidas. | MVP |

## 4. Disponibilidad y confiabilidad

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-012 | El despliegue reproducible con un solo comando documentado permite levantar el sistema completo desde cero. | MVP |
| RNF-013 | Ante un interbloqueo transitorio, el servicio de inventario reintenta la transacción completa un número acotado de veces sin aplicar efectos parciales. | MVP |
| RNF-014 | Los trabajos en cola de reportes reintentan fallos transitorios y registran el resultado final visible para el solicitante. | MVP |

## 5. Mantenibilidad

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-015 | La lógica de inventario reside en servicios de dominio independientes del protocolo HTTP, reutilizables desde consola o cola. | MVP |
| RNF-016 | El código sigue el estilo fijado por la herramienta de formato del ecosistema PHP y revisiones por fase. | MVP |
| RNF-017 | El contrato OpenAPI versionado acompaña cada cambio de la API para evitar deriva entre documentación e implementación. | MVP |
| RNF-018 | El monorepo conserva una sola historia de versiones con la especificación en `docs/`, el backend y el frontend separados por carpetas. | MVP |

## 6. Verificabilidad

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-019 | La suite automatizada cubre pruebas unitarias, de integración transaccional, de autorización y de concurrencia con transacciones reales de MySQL. | MVP |
| RNF-020 | Las pruebas de concurrencia reproducen los escenarios C-01 a C-04 del dominio de inventario y verifican sus invariantes al final. | MVP |
| RNF-021 | La integración continua ejecuta la suite completa y bloquea cambios que rompan garantías del inventario. | MVP |

## 7. Portabilidad

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-022 | El entorno canónico de contenedores reproduce base de datos, backend y frontend sin depender de herramientas del equipo local. | MVP |
| RNF-023 | La aplicación opera sobre los componentes fijados: PHP 8.4, Laravel 13.35, MySQL 8.4 LTS con InnoDB, Angular 22.2, Node 22 o 24 LTS y TypeScript 6.0. | MVP |
| RNF-024 | La interfaz web responde de forma utilizable en escritorio y en pantallas reducidas de bodega sin requerir aplicación nativa. | MVP |

## 8. Usabilidad y accesibilidad

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-025 | La interfaz cumple WCAG 2.1 nivel AA en contraste, foco visible, navegación por teclado y etiquetas de formularios. | MVP |
| RNF-026 | Todo mensaje de error aparece en español, identifica el campo o producto afectado y sugiere la corrección. | MVP |

## 9. Observabilidad

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-027 | La aplicación registra eventos operativos y errores con nivel, contexto y marca temporal consultable por el Administrador. | MVP |
| RNF-028 | Cada documento confirmado o revertido deja rastro cruzado entre kardex, existencias y bitácora para reconstruir la operación. | MVP |

## 10. Integridad de datos

| ID | Requisito | Fase |
| --- | --- | --- |
| RNF-029 | Cantidades, costos y totales usan tipos decimales exactos; ningún cálculo de dinero o stock usa punto flotante. | MVP |
| RNF-030 | Las unicidades de SKU, código de almacén, identificador público, consecutivo, clave de idempotencia y par producto y almacén se garantizan con restricciones únicas en la base de datos. | MVP |

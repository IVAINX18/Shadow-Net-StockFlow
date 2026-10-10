# Estrategia de pruebas - Shadow-Net StockFlow

**Estado del documento:** v1.0 (planificación). **Fecha:** 10/10/2026. **Autor:** Shadow-Net Technologies.

Este documento define cómo pruebo el sistema: pirámide de pruebas, pruebas unitarias, de integración transaccional, de autorización, de concurrencia, de extremo a extremo, objetivos de cobertura y criterio de terminado por fase. Las garantías que debo demostrar vienen de [inventory-domain.md](../architecture/inventory-domain.md) (§10 y §11), el modelo que verifico de [database-design.md](../architecture/database-design.md) y las decisiones de herramientas de [architecture-decisions.md](../architecture/architecture-decisions.md) (ADR-005, ADR-010). El marco de calidad general lo fija [project-charter.md](../planning/project-charter.md) (CG-02, CG-03, CG-05, OA-05).

## 1. Pirámide de pruebas

Organizo la suite en cuatro niveles, de base ancha a cúspide estrecha:

1. **Unitarias.** Rápidas, sin base de datos. Verifican cálculos y reglas puras del dominio.
2. **Integración transaccional.** Ejercitan servicios reales contra MySQL real. Verifican transacciones, rollbacks e invariantes.
3. **Autorización.** Verifican que cada endpoint niega lo que el rol o el alcance por almacén no permite, siempre en el servidor.
4. **Concurrencia y extremo a extremo.** Las primeras lanzan operaciones paralelas reales contra MySQL; las segundas recorren la aplicación desde Angular hasta la API.

La base (niveles 1 y 2) contiene la mayoría de los casos. La cúspide (niveles 3 y 4) contiene menos casos, pero cada uno protege una garantía crítica del negocio.

## 2. Herramientas

Ejecuto el backend con Pest 5 como corredor principal, construido sobre PHPUnit 13, según ADR-010. No instalo un segundo corredor principal: las aserciones subyacentes son de PHPUnit, así que el aprendizaje se transfiere sin duplicar configuración.

En el frontend uso el corredor de pruebas de Angular (Karma o su sucesor configurado en el proyecto) para pruebas unitarias de componentes y servicios. Para las pruebas de extremo a extremo uso Playwright [ESTIMACIÓN], que recorre la SPA contra la API real.

## 3. Pruebas unitarias (Pest)

Cubren la lógica que no necesita base de datos, según el §11.1 de [inventory-domain.md](../architecture/inventory-domain.md):

- Cálculo del promedio ponderado móvil con entradas sucesivas, incluido el caso de cantidad previa cero.
- Transiciones de estado válidas e inválidas del documento (`borrador` a `confirmado` o `descartado`, `confirmado` a `revertido`, terminales sin salida).
- Derivación de dirección y signo por tipo de documento (origen `salida` y destino `entrada` en transferencias).

Cada caso documenta la regla que verifica (por ejemplo, RN-INV-02 o RN-INV-11) para que un estudiante conecte la prueba con la regla de negocio.

## 4. Pruebas de integración con MySQL real

Ejecuto todas las pruebas de integración contra MySQL 8.4 real, nunca contra SQLite. El motivo es técnico: SQLite no reproduce el bloqueo de filas de InnoDB ni el comportamiento de concurrencia de `SELECT ... FOR UPDATE`, así que una prueba que pasa en SQLite puede esconder una sobreventa real. ADR-005 y ADR-007 lo prohíben para este núcleo, y el §11.4 del dominio lo repite.

Cada prueba de integración sigue el mismo patrón: prepara datos, ejecuta el caso de uso a través del servicio (no del controlador), y verifica el estado final y las invariantes. Casos obligatorios, del §11.2 de [inventory-domain.md](../architecture/inventory-domain.md):

- La entrada aumenta la existencia y escribe su movimiento; el kardex cuadra (I-02).
- La salida válida disminuye la existencia y escribe su movimiento.
- La salida mayor al stock se rechaza y no deja ningún efecto parcial (rollback completo).
- La transferencia descuenta origen y acredita destino en una sola transacción (I-03).
- La transferencia con origen igual a destino se rechaza.
- El ajuste sin motivo se rechaza.
- La reversión compensa un documento confirmado sin borrarlo (I-04).
- La idempotencia: dos confirmaciones con la misma clave producen un solo efecto (I-06).

## 5. Pruebas de autorización

Verifican CG-05 del acta: un usuario sin permiso sobre un almacén no puede operar ni consultar sus datos, y la verificación ocurre en el servidor. Casos mínimos, del §11.3 del dominio:

- Un usuario sin acceso al almacén no puede crear ni confirmar documentos de ese almacén (respuesta 403, sin efecto en la base).
- Un auditor no puede confirmar documentos; solo lee kardex, valorización y bitácora.

Cada endpoint nuevo del inventario incorpora su prueba de denegación antes de fusionarse.

## 6. Pruebas de concurrencia (C-01 a C-04)

Son las pruebas que demuestran que las garantías transaccionales funcionan bajo presión. Lanzo transacciones paralelas reales contra MySQL y verifico las invariantes al final. Reproduzco los cuatro escenarios del §11.4 de [inventory-domain.md](../architecture/inventory-domain.md):

- **C-01 (sobreventa).** Existencia de 10 unidades; dos salidas concurrentes de 8. Espero que exactamente una se confirme y la otra falle por stock insuficiente. La existencia final es 2, nunca negativa (I-01).
- **C-02 (transferencias cruzadas).** Dos transferencias A a B y B a A del mismo producto en paralelo. Espero que ninguna deje saldo negativo y que ningún interbloqueo quede sin resolver: el servicio reintenta la transacción completa un número acotado de veces.
- **C-03 (primera entrada concurrente).** Dos entradas simultáneas para un producto sin fila previa en `inventories`. Espero una sola fila con la suma de ambas cantidades, gracias a la restricción única `(product_id, warehouse_id)` y al reintento de lectura con bloqueo.
- **C-04 (doble confirmación).** Dos confirmaciones simultáneas del mismo documento. Espero que solo una aplique efectos y la otra se rechace por estado.

## 7. Pruebas de extremo a extremo (Angular más API)

Verifican los flujos completos que CG-01 del acta exige: registrar una entrada, una salida y una transferencia desde la interfaz, con el kardex y las existencias coherentes al final. Se ejecutan con Playwright [ESTIMACIÓN] contra la SPA servida y la API real, con MySQL real detrás.

Mantengo pocos escenarios e2e, pero representativos: inicio de sesión, creación y confirmación de una entrada, consulta del kardex, e intento de salida sin stock con su mensaje de error. La lógica de negocio no se prueba aquí; se prueba en los niveles inferiores. El e2e solo confirma que las piezas encajan.

## 8. Verificación de invariantes con SQL (I-02)

Cierro cada prueba de integración y concurrencia comprobando la invariante I-02: el saldo materializado debe igualar la suma del kardex. Uso la consulta de verificación de la sección 9 de [database-design.md](../architecture/database-design.md):

```sql
-- I-02: el saldo materializado debe igualar la suma del kardex.
SELECT i.product_id, i.warehouse_id, i.quantity,
       COALESCE(SUM(m.quantity_delta), 0) AS kardex_total
FROM inventories i
LEFT JOIN inventory_movements m
  ON m.product_id = i.product_id AND m.warehouse_id = i.warehouse_id
GROUP BY i.product_id, i.warehouse_id, i.quantity
HAVING i.quantity <> COALESCE(SUM(m.quantity_delta), 0);
```

Si la consulta devuelve filas, existe deriva y la prueba falla. Extiendo el mismo enfoque a I-01 (ninguna cantidad negativa) e I-03 (simetría de deltas en transferencias) con consultas equivalentes.

## 9. Objetivos de cobertura

- **Núcleo de inventario** (servicios transaccionales, máquina de estados, valorización): cobertura de líneas igual o superior al 90 por ciento, con todos los casos del §11 cubiertos.
- **Resto del backend** (autenticación, catálogos, reportes, auditoría): cobertura igual o superior al 70 por ciento.
- **Frontend**: cobertura de servicios de datos y de componentes críticos (documentos, kardex); no persigo un porcentaje global a costa de pruebas sin valor.

La cobertura orienta, no sustituye el criterio: una prueba de concurrencia que pasa vale más que diez pruebas de getters.

## 10. Qué significa "terminado" por fase

- **Fase de fundación.** Migraciones en orden, seeders base y suite Pest vacía pero ejecutable en CI.
- **Fase de dominio.** Servicios transaccionales con pruebas unitarias, de integración, de autorización y de concurrencia C-01 a C-04 en verde, e I-02 verificada.
- **Fase de API.** Endpoints versionados con pruebas de autorización por endpoint y contrato OpenAPI actualizado.
- **Fase de frontend.** Componentes con sus pruebas unitarias y escenarios e2e de CG-01 en verde.
- **Fase de cierre.** Suite completa en verde en integración continua, cobertura del núcleo sobre el umbral y bitácora al día.

Ninguna fase se declara terminada con pruebas de concurrencia pendientes o con SQLite sustituyendo a MySQL en el núcleo.

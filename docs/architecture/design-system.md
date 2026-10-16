# Sistema de diseno - Shadow-Net StockFlow

**Estado del documento:** v1.2 (modelo de superficie, neutros, tipografia, ritmo y lineas). **Fecha:** 15/10/2026. **Autor:** Shadow-Net Technologies.

Este documento es la **fuente unica** de la identidad visual de la SPA: sistema de componentes, tokens, temas, tipografia, color, forma, densidad, estados de interfaz y la lista de verificacion que una pantalla debe pasar antes de darse por terminada. La arquitectura de la SPA (carpetas, estado, interceptores, rutas) esta en [frontend-architecture.md](./frontend-architecture.md); los requisitos de accesibilidad en [../planning/non-functional-requirements.md](../planning/non-functional-requirements.md) (RNF-024, RNF-025, RNF-026); los contratos de presentacion de datos en [api-design.md](./api-design.md).

**Cambios de la v1.1 respecto de la v1.0:** tipografia (seccion 6), vocabulario de forma y realimentacion tactil (seccion 7), ritmo de espaciado por tokens (seccion 8), `MOTION_INTENSITY` de 2 a 3 (seccion 1), adaptacion de la referencia de gusto externa (seccion 15) y casillas nuevas de verificacion (seccion 16).

**Cambios de la v1.2 respecto de la v1.1:** modelo de superficie por tokens (seccion 3), neutros con croma cero (seccion 4), jerarquia tipografica (seccion 6), ritmo de 32 px (seccion 8), sistema de lineas en dos niveles (secciones 7 y 13) y acento sin cambios, pendiente del propietario (seccion 15). Verificacion de la unidad, ya medida en sesion previa y registrada en la bitacora: lint limpio, 40 pruebas en 9 ficheros en verde, compilacion de produccion de 342,67 kB iniciales sin avisos de presupuesto, 44 capturas en 1440/1024/768/375 en ambos temas, cero desbordes de titulo a 375 px en las 9 rutas y contrastes medidos sobre estilos calculados reales.

## 1. Lectura de diseno y alcance

[DECISION] **Lectura de diseno:** back-office ERP para operadores de bodega, jefes de almacen, auditores y administradores, en escritorio y en pantallas reducidas de bodega, con un lenguaje de informacion densa, confianza y accesibilidad critica, apoyado en **Angular Material 3** como sistema oficial.

[DECISION] **Diales de interfaz:** `DESIGN_VARIANCE 3` (rejilla predecible, simetria, alineacion estricta), `MOTION_INTENSITY 3` (interfaz estatica: solo transiciones breves, estados de `:hover` y `:active`, y realimentacion fisica al pulsar) y `VISUAL_DENSITY 7` (tablas y datos operativos densos). Los tres valores se derivan del caracter del producto: software de operacion, no de promocion. El movimiento sube de 2 a 3 en la v1.1 porque se admite realimentacion tactil explicita; sigue sin existir animacion decorativa.

[DECISION] La guia de gusto visual usada para cerrar estas decisiones declara explicitamente que los paneles de administracion, las tablas de datos y los formularios de varios pasos quedan **fuera de su alcance**. Por eso de este documento se excluye toda la maquinaria de paginas de marketing (hero, cuadriculas tipo bento, marquesinas, rotulaciones decorativas, prohibiciones de paleta para consumo premium) y se adopta unicamente lo que si transfiere a un panel de administracion: protocolo de temas, disciplina de color, consistencia de forma, ciclos completos de estado, patrones de formulario, tipografia para datos, contraste, rendimiento, respeto al movimiento reducido y verificacion previa a la entrega.

## 2. Sistema de componentes

[DECISION] **Angular Material 22.2.x y Angular CDK** son el unico sistema de componentes. No se incorpora Tailwind CSS ni ninguna otra biblioteca visual.

Motivos: la documentacion del proyecto ya fija Material 22.2.x para tablas, formularios, dialogos y navegacion accesible (ADR-006, [frontend-architecture.md](./frontend-architecture.md) seccion 10); mezclar dos sistemas obliga a mantener dos escalas de espaciado, dos paletas y dos modelos de accesibilidad; y la regla del proyecto prohibe anadir infraestructura por moda. Una biblioteca de estado global (NgRx) tambien queda descartada por sobredimensionada.

No se anaden dependencias visuales sin justificarlo en este documento y en la bitacora. Las unicas anadidas en la v1.1 son los paquetes de fuentes autoalojadas que justifica la seccion 6.

## 3. Tokens

[DECISION] Todo color, tipografia, radio, espaciado y elevacion proviene de los **tokens de sistema de Material 3** que genera `mat.theme()` (familia `--mat-sys-*`), expuestos como propiedades personalizadas de CSS. Los componentes no declaran colores literales, ni hexadecimales, ni `rgb()`, ni transparencias inventadas.

El proyecto anade sus propias propiedades con el prefijo `--app-` para lo que Material no cubre: escala de espaciado, escala de apilamiento y ritmo de composicion (seccion 8). No se redefine ningun rol de color ni de tipografia de Material.

[DECISION] **Unica excepcion de color, y esta cerrada.** Material 3 no define un rol de advertencia, y como toda la paleta se deriva de una semilla unica, los roles primario y terciario colapsan en el mismo valor. Consecuencia medida: los tonos de estado no caben en cinco colores distintos usando solo roles de Material. Por eso el proyecto declara un unico par de propiedades semanticas de advertencia, resueltas con `light-dark()` para los dos temas:

| Token | Tema claro | Tema oscuro |
| --- | --- | --- |
| `--app-status-warning-container` | `#fef3c7` | `#4a3208` |
| `--app-status-on-warning-container` | `#78350f` | `#fde68a` |

Los valores provienen de la familia de ambar de una paleta de uso comun. Su contraste se verifica en ambos temas (seccion 16) y no se anade ningun otro color: los demas tonos de estado usan roles de Material (`surface-variant`, `secondary-container`, `primary-container`, `error-container`).

Reglas de uso de la jerarquia de superficie:

| Rol | Uso |
| --- | --- |
| `surface` | Fondo general de la aplicacion. |
| `surface-container-low` | Barra de navegacion lateral y agrupaciones de fondo. |
| `surface-container` | Tarjetas de indicadores y contenedores de contenido. |
| `surface-container-high` | Elementos elevados sobre una tarjeta: menus, encabezados fijos de tabla. |
| `outline-variant` | Divisores y bordes de 1 px entre filas y secciones. |
| `primary` / `on-primary` | Acento unico, acciones principales y estado activo de la navegacion. |
| `error`, `tertiary`, `secondary` y sus variantes | Solo estado semantico (error, advertencia, exito, informacion). Nunca decoracion. |

[DECISION] **Modelo de superficie por tokens.** Los componentes no apuntan a los roles de Material directamente, sino a tres propiedades declaradas en `frontend/src/styles.scss`:

| Token | Tema claro | Tema oscuro | Nivel y uso |
| --- | --- | --- | --- |
| `--app-surface-canvas` | `#ffffff` | `surface` | Nivel 0: el lienzo de la pagina. |
| `--app-surface-paper` | `#f5f5f5` | `surface-container-low` | Nivel 1: zonas secundarias o agrupadas, barra lateral y barras de herramientas del armazon. |
| `--app-surface-card` | `#ffffff` | `surface-container` | Nivel 2: tarjetas. |

El modelo refleja los niveles 0 (lienzo), 1 (papel) y 2 (tarjeta) de la referencia: tarjetas en blanco sobre blanco separadas por linea fina, con el papel para las zonas agrupadas.

## 4. Color

[DECISION] **Semilla de paleta: verde azulado profundo.** Material 3 deriva de esa unica semilla toda la paleta tonal y asigna los roles semanticos, de modo que el proyecto no mantiene una lista de colores a mano.

- Un solo acento (el primario) en toda la aplicacion y en todas las pantallas; queda bloqueado por coherencia.
- Los colores semanticos comunican estado y nunca se usan para decorar.
- Sin gradientes decorativos, sin saturaciones altas, sin acentos innecesarios.
- Sin negro puro ni blanco puro como superficie general: la paleta tonal de Material 3 entrega grises y blancos sucios, que conservan profundidad.
- El color nunca es el unico portador de informacion (ver seccion 13).
- **Cinco tonos de estado, cinco colores distintos.** Neutral, informativo, exito, advertencia y error deben poder distinguirse entre si por color, ademas de por texto e icono. La distincion se mide sobre los colores reales en pantalla, no se supone a partir del nombre de la clase.
- Todo contraste se verifica sobre los valores reales de los tokens, en tema claro y en tema oscuro.

[DECISION] **Neutros con croma cero.** `frontend/tools/generate-palette.mjs` emite las escalas `neutral` y `neutral-variant` con **croma cero** (`TonalPalette.fromHueAndChroma(0, 0)`); primario, secundario, terciario y error siguen derivandose de la semilla de marca `#00695C`.

Motivo medido: Material 3 impone un croma minimo a sus escalas de neutros, asi que los neutros heredaban el tinte verde de la semilla. Cambiar la semilla por un gris no lo corrige, medido: una semilla gris entrega `#f8fafa` frente al `#f7faf8` vigente entonces, 2 unidades de diferencia en el canal azul, invisibles. El croma cero es el unico mecanismo que elimina el tinte. Resultado: grises puros (`98: #f9f9f9`, `50: #777777`, `6: #131313`). Trampa encontrada al hacerlo: la variante neutra con croma 4 y tono 0 sale rosada (`#cfc4c5`), porque el tono 0 se lee calido en CAM16, asi que ahi tambien hay que usar croma cero.

## 5. Temas claro y oscuro

[DECISION] La aplicacion nace con **tema claro y tema oscuro**, resueltos con los tokens de Material 3. El tema inicial sigue la preferencia del sistema operativo (`prefers-color-scheme`) y existe un conmutador manual que persiste la eleccion del usuario.

- Una pantalla tiene **un solo tema**: ninguna seccion invierte su fondo respecto al resto. Las superficies de la seccion 3 dan la diferenciacion sin cambiar de tema.
- La jerarquia visual debe funcionar igual en ambos temas: lo que destaca en claro destaca en oscuro.
- Ninguna pantalla se da por terminada sin haberla visto en los dos temas.

## 6. Tipografia

[DECISION] **Inter** (pesos 400, 500 y 600) para texto general y titulares, y **JetBrains Mono** (pesos 400 y 500) para identificadores, codigos y metadatos tecnicos donde la alineacion monoespaciada ayuda a comparar. Ambas se autoalojan desde el paquete del proyecto, sin depender de un CDN y sin `<link>` a servicios de fuentes. Se retira Roboto.

Motivos: la referencia de gusto del repositorio (`DESIGN.md`, seccion Tipografia) declara Inter con peso medio y tracking cerrado como sustituta de su titular, y una monoespaciada para los metadatos tecnicos; es la palanca de mayor mejora visual por unidad de riesgo en un rediseno. La guia de gusto que rige estas decisiones desaconseja Inter por defecto, pero admite la excepcion explicita para interfaces neutras al estilo Linear y de accesibilidad critica, que es exactamente este caso; la excepcion queda registrada aqui y en la bitacora.

- Se usa la escala tipografica de Material 3; no se inventan tamanos fuera de ella.
- **Jerarquia aplicada:** titulo de pagina `headline-large` de 32 px; titulo del acceso `headline-medium` de 28 px; titulos de seccion y de tarjeta `title-large` de 22 px; valor de indicador `headline-medium` de 28 px (sin cambios); cuerpo `body-medium` de 14 px. Jerarquia resultante de 32 > 28 > 22 > 16.
- **Peso del titular centralizado en `--app-title-weight: 500`**, que es el tratamiento de titular de la referencia (Inter en peso 500 con tracking cerrado; `.app-tight` con `-0.02em` es el equivalente existente de su sustituto Satoshi). El titulo de pagina se aplica en un solo lugar, el encabezado compartido de pagina, asi que todas las pantallas lo heredan.
- **Titulares con tracking cerrado:** los roles `display`, `headline` y `title` se componen con `letter-spacing: -0.02em` mediante la utilidad `.app-tight`; el peso medio (no negrita) es lo que da caracter sin gritar.
- **Numeros tabulares activados** en toda celda numerica, en los valores de las tarjetas de indicadores y en las columnas de cantidades, costos y valores: es lo que permite comparar cifras en vertical.
- La monoespaciada se reserva a datos tecnicos: codigos de almacen, claves de documento, SKU, rutas de endpoint. No se usa para texto corrido.
- Texto en espanol; codigo y nombres tecnicos en ingles (SD-06).
- No se reduce el tamano del texto hasta perjudicar la legibilidad, ni siquiera para ganar densidad.
- No se usa tipografia con serifa: es una interfaz de datos.
- Se precargan los pesos 400 y 500 de Inter; no se precarga la monoespaciada, que no condiciona el primer render.

## 7. Forma, elevacion y separacion

[DECISION] **Vocabulario unico de radios.** Fuera de esta tabla no se usa ningun radio en el proyecto:

| Elemento | Rol de Material | Valor |
| --- | --- | --- |
| Etiquetas, insignias, avatares | `corner-full` | pildora |
| Botones | `corner-small` | 8 px |
| Campos de formulario | `corner-extra-small` | 4 px |
| Tarjetas y contenedores de contenido | `corner-medium` | 12 px |
| Superficies grandes (acceso, dialogos) | `corner-large` | 16 px |

[DECISION] **El contenedor se define con borde, no con sombra.** La elevacion esta prohibida como decoracion: un bloque se separa con un borde de 1 px (`outline-variant`) o con espacio en blanco. La sombra se reserva a lo que de verdad flota sobre la pagina (menu, dialogo, capa superpuesta) y va entintada con el tono del fondo, nunca negro puro sobre fondo claro.

[DECISION] **Sistema de lineas en dos niveles.** La linea decorativa y el borde de control se separan y usan los dos tokens propios de Material 3, que ya traen el significado correcto:

| Uso | Token | Tema claro | Tema oscuro |
| --- | --- | --- | --- |
| Decorativa (bordes de tarjeta, divisores) | `outline-variant` | `#c6c6c6` | `#474747` |
| Borde de control (campos, botones, casillas) | `outline` | `#777777` | `#919191` |

La decorativa es sutil a proposito (1.71:1 en claro y 2.00:1 en oscuro); el borde de control alcanza 4.48:1 en claro y 5.90:1 en oscuro, medidos sobre estilos calculados reales. Requisito explicito: el borde de un control debe alcanzar 3:1 (WCAG 1.4.11), porque puede ser lo unico que identifica al control. Nota honesta: antes de este cambio el contorno de control media 1.62:1 en claro y 1.99:1 en oscuro y por tanto no cumplia; era un defecto previo encontrado al medir, no algo que este cambio introdujera.

[DECISION] **Realimentacion fisica al pulsar.** Toda accion interactiva responde al pulsarse con un hundimiento de 1 px o una escala de 0.98, y su transicion dura entre 120 y 160 ms. No hay ninguna animacion que no comunique un cambio de estado.

- La separacion por defecto entre filas y bloques es un divisor de 1 px (`outline-variant`) o espacio en blanco, no una tarjeta.
- Las tarjetas se usan solo cuando la elevacion comunica jerarquia real; no se envuelve todo en tarjetas.

## 8. Densidad y espaciado

[DECISION] Las vistas de datos (tablas, listados, kardex, existencias) usan **densidad compacta** de Material (`density: -2`), con divisores sutiles y alineacion consistente, para alcanzar la densidad visual 7 declarada en la seccion 1.

[DECISION] **Ritmo de composicion por tokens.** El espaciado de bloque no se improvisa por pantalla; se compone con estas propiedades, todas derivadas de la escala de 4 px:

| Token | Valor | Uso |
| --- | --- | --- |
| `--app-page-gutter` | 32 px (24 px por debajo de 768 px) | Margen interior del area de contenido. |
| `--app-section-gap` | 32 px (24 px por debajo de 768 px) | Separacion entre bloques de una pantalla. |
| `--app-card-padding` | 16 px | Relleno interior de tarjetas y contenedores. |
| `--app-element-gap` | 8 px | Separacion entre elementos de un mismo bloque. |
| `--app-space-1` a `--app-space-8` | 4 a 32 px | Escala general de la seccion. |

`--app-page-gutter` y `--app-section-gap` son ahora 32 px (`--app-space-8`) y bajan a 24 px (`--app-space-6`) por debajo de 768 px; el relleno de tarjeta (16 px) y la separacion entre elementos (8 px) no cambian. La referencia usa 64 px porque es una pagina de promocion; 32 px es la traduccion honesta para un panel de operacion denso.

[DECISION] En las pantallas de operacion de bodega se mantienen **objetivos tactiles de al menos 44 px** y la densidad compacta no se aplica: ahi mandan RNF-024 y la accesibilidad por encima de la densidad.

No se usan margenes arbitrarios.

## 9. Estados de interfaz

Toda vista que obtiene datos del backend implementa los cuatro estados, no solo el camino feliz:

| Estado | Como se resuelve |
| --- | --- |
| Carga | Esqueleto con la forma del contenido final (filas de tabla, tarjetas), no un indicador circular generico. |
| Vacio | Estado compuesto que explica que falta y como poblarlo, con la accion disponible si el usuario tiene permiso. |
| Error | Mensaje en espanol junto al contenido afectado; en formularios, por campo. Se distingue el error de validacion, el de permisos y el de servidor. |
| Exito | Confirmacion breve y no intrusiva; el resultado de una operacion se refleja en los datos, no en un aviso permanente. |

Las acciones interactivas dan respuesta fisica al pulsarse (seccion 7). Las confirmaciones y reversiones usan dialogos, nunca un `confirm` del navegador.

## 10. Formularios

[DECISION] Etiqueta **arriba** del campo, texto de ayuda cuando aporte, y mensaje de error **debajo** del campo al que pertenece. Nunca se usa el marcador de posicion como etiqueta.

- Formularios reactivos tipados (ADR-014); el formulario valida la presentacion, nunca sustituye la validacion del backend.
- Los errores 422 del backend se mapean campo por campo usando el detalle del contrato (`{field, code, message}`), que ya llega en el sobre de error.
- Los campos deshabilitados y los de solo lectura se distinguen con claridad.
- El foco es visible en todos los controles y su anillo se separa del borde del campo.

## 11. Contratos de presentacion de datos

Reglas que la interfaz no puede romper porque el contrato de la API ya las fijo en [api-design.md](./api-design.md):

- **Decimales como cadenas:** cantidades, costos y valores llegan como `"12.500"`. Se presentan a partir de la cadena; **no se convierten a `float`** en ningun punto de la presentacion.
- **Fechas en UTC ISO-8601:** la presentacion en la zona `America/Bogota` es responsabilidad del cliente.
- **Paginacion:** `page` y `per_page` con `meta` y `links`; el tamano de pagina maximo lo impone el contrato.
- **Ninguna cifra inventada:** no se muestran cifras, tendencias ni variaciones porcentuales que no provengan de datos reales del backend. Si no hay periodo de comparacion, no se muestra variacion.

## 12. Copy de interfaz

- Todo el texto visible va en espanol.
- **Sin guiones largos** ( `—` ni `–` ) en el texto de la interfaz: se usan guion corto, punto, coma o parentesis.
- Todo mensaje de error identifica el campo o el producto afectado y sugiere la correccion (RNF-026).
- No se inventan nombres de persona, empresas, ni cifras de relleno. En los datos de demostracion se usan nombres realistas y coherentes con el contexto colombiano.
- No se usan verbos de relleno ni promesas de producto.
- Antes de entregar una pantalla, se relee cada cadena visible: titulos, etiquetas de boton, ayudas, textos de error y de estado vacio.

## 13. Accesibilidad

RNF-025 exige **WCAG 2.1 nivel AA**. Se verifica:

- Contraste de texto y de controles en ambos temas; el borde de un control debe alcanzar 3:1 segun WCAG 1.4.11 (ver seccion 7).
- Foco visible en todos los elementos interactivos y orden de tabulacion coherente.
- Navegacion completa por teclado, incluidas tablas, menus y dialogos.
- Etiquetas accesibles y mensajes de error asociados a su campo.
- Tablas con estructura semantica (encabezados de columna declarados, no solo celdas con estilo).
- Ningun estado depende exclusivamente del color: siempre acompanado de texto o icono.
- Respeto a la preferencia de movimiento reducido del sistema operativo.
- Adaptacion a escritorio, tableta y movil. En pantallas pequenas se priorizan acciones operativas, legibilidad y accesibilidad; no se comprime la interfaz de escritorio.

## 14. Rendimiento y capas

- Objetivos: LCP menor a 2.5 s, INP menor a 200 ms, CLS menor a 0.1.
- Los presupuestos del compilador estan fijados en `angular.json` (inicial 500 kB de aviso y 1 MB de error; estilos por componente 4 kB y 8 kB). Se miden y se documentan al cerrar cada unidad; si se revisan, se hace con la medicion delante.
- Carga perezosa por funcionalidad, como ya establece la arquitectura.
- Las fuentes se autoalojan, se precargan y no bloquean el primer render. Solo se precargan los pesos que usa el primer pintado.
- Los indices de apilamiento se declaran en una escala unica del proyecto; no se usan valores sueltos por pantalla.

## 15. Adaptacion de las referencias visuales

### 15.1 Composicion de referencia (armazon)

La composicion de referencia aporta el armazon, no la piel. Lo que se adopta y lo que se corrige:

| Rasgo de la referencia | Decision |
| --- | --- |
| Navegacion lateral fija con jerarquia, iconos y seccion activa resaltada | Se adopta. Grupo de opciones relacionadas y contraccion en pantallas apropiadas. |
| Barra superior con marca, busqueda, notificaciones, tema y perfil | Se adopta, sin controles decorativos que aparenten funcionar. |
| Fila de tarjetas de indicadores con icono discreto y valor destacado | Se adopta la composicion. |
| Tarjetas de indicador con relleno de color saturado distinto en cada una | Se corrige: las tarjetas comparten un unico tratamiento de superficie y el acento se reserva al icono, porque la saturacion por bloques y los acentos multiples van contra la disciplina de color del proyecto. |
| Barra lateral oscura dentro de un tema claro | Se corrige: la navegacion usa una superficie diferenciada del mismo tema. Invertir una zona de la pantalla es cambiar de tema a mitad de la pagina, lo que rompe la coherencia y el contraste. |
| Graficos integrados en el contenido, con proposito operativo | Se adopta, pero solo con datos reales y solo los que respondan a una decision de operacion. |
| Tablas con encabezados claros, estados y acciones por fila | Se adopta, con paginacion, ordenamiento, filtros, estados de carga, vacio y error. |
| Etiquetas pequenas en mayusculas sobre cada bloque | Se adopta como titulo funcional de tarjeta, no como rotulo decorativo, y con moderacion. |
| Panel flotante superpuesto con un anillo de porcentaje | No se adopta: es decoracion superpuesta que compite con el contenido y no responde a una necesidad operativa. |
| Fondo neutro con superficies diferenciadas | Se adopta mediante los roles de superficie de la seccion 3. |

### 15.2 Referencia de gusto externa (`DESIGN.md`)

El repositorio incorpora `DESIGN.md` como referencia de gusto de una interfaz de producto ajena, con vocacion de aprender su sistema y no su identidad. El detalle de la adopcion:

| Rasgo de la referencia externa | Decision |
| --- | --- |
| El contenedor se define con borde de 1 px y no con sombra | Se adopta. Ya era la practica del proyecto; ahora queda escrito, verificado y con la elevacion prohibida como decoracion (seccion 7). |
| La tipografia es la primera palanca de mejora, con titular de peso medio y tracking cerrado | Se adopta: cambian las familias y se cierra el tracking de los titulares (seccion 6). |
| Un solo acento gastado con avaricia: una accion principal por pantalla, el resto en contorno, texto o tono | Se adopta. |
| Vocabulario fijo y corto de radios, con la pildora reservada a etiquetas e insignias | Se adopta como tabla unica de la seccion 7; los botones dejan de ser pildora. |
| Densidad compacta con filas que respiran y numeros alineados | Se adopta. |
| Realimentacion fisica al pulsar | Se adopta (seccion 7). |
| Superficies planas, blanco sobre blanco, separadas por linea fina | Se adopta mediante los roles de superficie. |
| El azul electrico de la referencia | **No se adopta:** la marca conserva su semilla verde azulada. Se aprende el sistema, no la identidad. |
| El relleno casi negro de la accion principal | **No se adopta:** el primario se mantiene en `#006b5e` en claro y `#56dbc5` en oscuro, y la accion principal rellena conserva el acento en lugar del relleno casi negro de la referencia. La decision queda pendiente del propietario. |
| Fondo de rejilla de puntos | **No se adopta:** es textura decorativa sin funcion operativa. |
| Interfaz solo en tema claro | **No se adopta:** el proyecto mantiene tema claro y oscuro (seccion 5). |
| Reglas de pagina de promocion (hero, marquesinas, rejillas bento, fotografia generada) | **No se adoptan:** la propia guia de gusto las declara fuera de alcance para paneles de administracion, y este documento las excluye en la seccion 1. |

## 16. Verificacion antes de entregar una pantalla

Ninguna pantalla se declara terminada si alguna casilla no se puede marcar con honestidad:

- [ ] Usa solo tokens; no hay colores literales ni tamanos inventados.
- [ ] Se ve y se recorre correctamente en tema claro y en tema oscuro.
- [ ] Se reviso la captura real de la pantalla en ambos temas antes de cerrarla.
- [ ] Contraste AA verificado en texto, botones, campos, ayudas y foco.
- [ ] Ningun boton ni campo queda ilegible sobre su fondo.
- [ ] El vocabulario de forma de la seccion 7 se cumple sin excepciones.
- [ ] Los titulares usan el tracking cerrado y las familias declaradas; ninguna pantalla carga fuentes de un CDN.
- [ ] Las acciones interactivas responden al pulsarse, con transicion breve y sin animacion decorativa.
- [ ] Estados de carga, vacio y error implementados, ademas del camino feliz.
- [ ] Los numeros usan cifras tabulares y los decimales se presentan desde la cadena del contrato.
- [ ] Las fechas se muestran en America/Bogota.
- [ ] Los iconos son de Material Symbols; no hay iconos dibujados a mano.
- [ ] Radios, sombras y espaciado coherentes con las secciones 7 y 8.
- [ ] Sin guiones largos ni cifras inventadas en el texto visible.
- [ ] Los tonos de estado se distinguen entre si por color medido en pantalla (no basta con que cambien de nombre de clase; una prueba en DOM simulado no puede comprobarlo).
- [ ] La tabla tiene estructura semantica, es navegable por teclado y sus acciones respetan permisos.
- [ ] El movimiento reducido del sistema se respeta.
- [ ] La pantalla se sostiene en 1440, 1024, 768 y 375 px.
- [ ] El peso compilado se midio y esta documentado.
- [ ] Ninguna regla de negocio del inventario vive en el componente.
- [ ] Ningun control decorativo aparenta funcionar.
- [ ] Una sola biblioteca de componentes: no hay mezcla de sistemas.

## 17. Referencias

- Referencia de gusto externa: [DESIGN.md](../../DESIGN.md) (raiz del repositorio).
- Arquitectura de la SPA: [frontend-architecture.md](./frontend-architecture.md).
- Decisiones de version: [architecture-decisions.md](./architecture-decisions.md).
- Contrato de la API y presentacion de datos: [api-design.md](./api-design.md).
- Requisitos de accesibilidad y usabilidad: [../planning/non-functional-requirements.md](../planning/non-functional-requirements.md).
- Estandares de codigo: [../development/coding-standards.md](../development/coding-standards.md).

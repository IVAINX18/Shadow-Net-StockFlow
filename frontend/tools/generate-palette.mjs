// Genero los mapas de paleta tonal M3 de Shadow-Net StockFlow a partir de una
// unica semilla de color, con la implementacion de referencia de Google
// (@material/material-color-utilities). Angular Material 22 no incluye un verde
// azulado profundo entre sus paletas y el contrato de diseno exige derivar toda
// la paleta de una sola semilla, asi que la genero aqui y la dejo confirmada en
// src/styles/_palette.scss. Se ejecuta con `npm run palette:generate`.
import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import {
  argbFromHex,
  hexFromArgb,
  themeFromSourceColor,
  TonalPalette,
} from '@material/material-color-utilities';

const SEED = '#00695C';
// Tonos estandar de una paleta M3 (los que Angular Material espera como claves).
const TONES = [0, 10, 20, 25, 30, 35, 40, 50, 60, 70, 80, 90, 95, 98, 99, 100];
// La paleta neutral de Angular Material incluye tonos intermedios adicionales
// (4, 6, 12, 17, 22, 24, 87, 92, 94, 96) que sostienen los roles
// surface-container-*; sin ellos esos roles salen vacios.

// Derivo el tema completo desde la semilla: es el mismo algoritmo M3 que usa el
// Material Theme Builder, de modo que los valores son los de referencia y no una
// aproximacion mia.
const theme = themeFromSourceColor(argbFromHex(SEED));

// Emito cada mapa con sus dieciseis tonos, que son los que consume el tema de
// Angular Material para asignar los roles de color.
const toTones = (palette, tones = TONES) =>
  tones.map((tone) => `    ${tone}: ${hexFromArgb(palette.tone(tone))},`).join('\n');

const toMap = (name, tones) => `$${name}: (\n${tones}\n  );`;

const NEUTRAL_TONES = [...new Set([...TONES, 4, 6, 12, 17, 22, 24, 87, 92, 94, 96])].sort(
  (a, b) => a - b,
);

const primary = toTones(theme.palettes.primary);
const secondary = toTones(theme.palettes.secondary);
const tertiary = toTones(theme.palettes.tertiary);
// Emito los neutros con croma cero: Material 3 fija un croma minimo en sus
// escalas neutras y ese minimo arrastra el tinte verde de la semilla al lienzo
// (hoy el canvas sale verdoso). Una semilla gris no sirve porque solo desplaza
// el matiz del tinte; con croma cero el gris es acromatico de verdad. La
// variante neutra tambien va a cero porque con croma 4 y matiz 0 el CAM16 la
// devuelve rosada y los bordes se verian calidos. El primario no se toca.
const neutralGrey = TonalPalette.fromHueAndChroma(0, 0);
const neutral = toTones(neutralGrey, NEUTRAL_TONES);
const neutralVariant = toTones(neutralGrey);
const error = toTones(theme.palettes.error);

const content = `// Archivo generado por tools/generate-palette.mjs. No editar a mano.
// Semilla: ${SEED}. Paleta tonal M3 derivada con @material/material-color-utilities.
//
// A mat.theme se le pasa $stockflow-brand-palette, que ademas de los tonos trae
// los sub-mapas secondary, neutral, neutral-variant y error. Es un requisito de
// mat.theme: sin esos sub-mapas, los roles de superficie, neutral y error salen
// vacios y el tema queda roto. Los mapas sueltos de abajo quedan como referencia
// tonal del proyecto.

${toMap('stockflow-primary-palette', primary)}

${toMap('stockflow-secondary-palette', secondary)}

${toMap('stockflow-tertiary-palette', tertiary)}

${toMap('stockflow-neutral-palette', neutral)}

${toMap('stockflow-neutral-variant-palette', neutralVariant)}

${toMap('stockflow-error-palette', error)}

// Paleta de marca completa que consume mat.theme en styles.scss.
$stockflow-brand-palette: (
${primary}
  secondary: (
${secondary}
  ),
  neutral: (
${neutral}
  ),
  neutral-variant: (
${neutralVariant}
  ),
  error: (
${error}
  ),
);
`;

const target = resolve(process.cwd(), 'src/styles/_palette.scss');
writeFileSync(target, content, 'utf8');
console.log(`Paleta M3 generada en ${target} desde la semilla ${SEED}.`);

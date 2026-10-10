# Imagen del frontend Angular 22 con etapas de desarrollo y produccion.
# Referencia: docs/architecture/deployment-architecture.md, seccion 3.

# Etapa de desarrollo: sirve con ng serve. El codigo se monta como volumen en
# docker-compose para habilitar la recarga en caliente.
FROM node:22-bookworm-slim AS dev
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
EXPOSE 4200
CMD ["npm", "start", "--", "--host", "0.0.0.0", "--poll", "1000"]

# Etapa de construccion: compila la SPA con tipado estricto.
FROM node:22-bookworm-slim AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Etapa de produccion: sirve los estaticos con nginx (imagen muy ligera).
# El generador de aplicaciones de Angular deposita el navegador en
# dist/frontend/browser, y eso es lo que copio al servidor web.
FROM nginx:alpine AS prod
COPY --from=build /app/dist/frontend/browser /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]

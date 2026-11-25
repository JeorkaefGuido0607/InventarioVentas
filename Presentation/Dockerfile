# Etapa 1: Construcción de Angular
FROM node:24-alpine AS build

WORKDIR /app

# Activar pnpm
RUN corepack enable

# Copiar archivos de dependencias
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./

# Instalar dependencias
RUN pnpm install --frozen-lockfile

# Copiar el código fuente
COPY . .

# Compilar Angular
RUN pnpm build

# Etapa 2: Servidor web
FROM nginx:alpine

# Copiar archivos compilados
COPY --from=build /app/dist/Presentation/browser /usr/share/nginx/html

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]

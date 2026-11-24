# Sistema de Inventario y Ventas — Back-end

Proyecto final de **Desarrollo de Software IV**, basado en la arquitectura del proyecto de clase *CitasMedicas*.
Esta entrega contiene el back-end: la **capa de datos**, la **capa de negocio** (API REST) y la base de datos MySQL, todo en contenedores Docker.

```
Cliente web / app móvil
        │  HTTP + JWT
        ▼
┌──────────────────────┐   HTTP (red interna)   ┌──────────────────────┐   SQL    ┌──────────────┐
│ web_negocio  :9090   │ ─────────────────────► │ web_datos  :8080     │ ───────► │ db  :3307    │
│ Business (Slim)      │                        │ Data (Slim+Eloquent) │          │ MySQL 9.7    │
│ Controllers          │                        │ Controllers          │          │ tablas, FK,  │
│ Services (reglas)    │                        │ Models (ORM)         │          │ SP, seeders  │
│ Validators, JWT, Rol │                        │ transacciones        │          └──────────────┘
└──────────────────────┘                        └──────────────────────┘
```

| Capa | Responsabilidad |
|---|---|
| **Business** (`/Business`) | API pública: autenticación JWT, autorización por roles, validación (`respect/validation`), reglas de negocio, cálculo de ventas (IVA 13 %), reportes y exportación CSV, Swagger y pruebas unitarias. |
| **Data** (`/Data`) | Persistencia: modelos Eloquent, consultas paginadas, transacciones, procedimientos almacenados y consultas de reportes. Solo la llama la capa de negocio. |
| **Base de datos** (`/db_dump`) | Scripts SQL: esquema con llaves primarias/foráneas y restricciones, procedimientos almacenados y datos iniciales. |

---

## 1. Levantar el proyecto

Requisitos: Docker Desktop.

```bash
docker compose up -d --build
```

- La primera vez tarda unos minutos (descarga imágenes e instala dependencias con Composer).
- MySQL ejecuta los scripts de `db_dump/` solo la **primera vez** que se crea el volumen. `web_datos` espera a que la base de datos esté lista (*healthcheck*).
- Si se cambian los scripts SQL, hay que borrar el volumen para que se vuelvan a ejecutar:

```bash
docker compose down -v
docker compose up -d
```

| Servicio | Contenedor | URL |
|---|---|---|
| API de negocio | `inv_web_negocio` | http://localhost:9090/api |
| Swagger UI | `inv_web_negocio` | http://localhost:9090/api/docs |
| Capa de datos | `inv_web_datos` | http://localhost:8080/api |
| MySQL | `inv_base_datos` | `localhost:3307` (usuario `root`, contraseña en `db_password.txt`) |

> Si CitasMedicas está corriendo, deténgalo antes (`docker compose down` en su carpeta): usan los mismos puertos. Los nombres de contenedor sí son distintos (`inv_...`), así que no chocan.

### Usuarios de prueba

| Usuario | Contraseña | Rol |
|---|---|---|
| `administrador` | `Admin2026#` | Admin |
| `vendedor01` | `Ventas2026#` | Vendedor |
| `vendedor02` | `Ventas2026#` | Vendedor |
| `bodeguero01` | `Bodega2026#` | Bodeguero |

### Pruebas unitarias

```bash
docker exec inv_web_negocio composer test
```

66 pruebas (PHPUnit 12) sobre los servicios de negocio y validadores. No necesitan la base de datos: usan un doble de la capa de datos (`tests/Fakes/FakeDataService.php`).

### Probar la API

- **Swagger UI:** http://localhost:9090/api/docs → `POST /api/auth/login` → copiar el token → botón **Authorize**.
- **REST Client (VS Code):** archivos en `Business/RESTRequest/`. Cada archivo hace su propio login (`# @name login`) y usa el token automáticamente.

---

## 2. Base de datos

| Tabla | Descripción |
|---|---|
| `roles`, `usuarios` | Seguridad. Contraseñas con `password_hash` (bcrypt). |
| `categorias`, `proveedores` | Catálogos de productos. |
| `productos` | Módulo principal. Código de barras único, precios, stock y stock mínimo. `CHECK (stock >= 0)`. |
| `clientes` | Incluye `latitud`/`longitud` para la ubicación capturada con el GPS de la app móvil. |
| `ventas`, `detalle_ventas` | Encabezado (montos, método de pago, estado) y líneas. |
| `movimientos_inventario` | Bitácora (kardex): cada cambio de stock con stock anterior y nuevo, usuario y motivo. |

**Procedimientos almacenados**

- `sp_registrar_movimiento`: Entrada / Salida / Ajuste con bloqueo de fila (`FOR UPDATE`) y validación de stock.
- `sp_anular_venta`: devuelve el stock, registra la anulación en la bitácora y marca la venta como `Anulada`.
- `sp_obtener_detalle_venta`: líneas de una venta.

Los datos iniciales incluyen 24 productos, 8 clientes, 5 proveedores y 140 ventas de los últimos 90 días (con su bitácora), para que el dashboard y los reportes tengan información desde el primer arranque.

---

## 3. API REST (capa de negocio)

Todas las rutas empiezan con `/api` y, excepto el login y la documentación, requieren `Authorization: Bearer <token>`.

### Roles

| Módulo | Admin | Vendedor | Bodeguero |
|---|:-:|:-:|:-:|
| Usuarios y roles | ✔ | | |
| Categorías y proveedores | ✔ | consulta | ✔ (sin eliminar) |
| Productos | ✔ | consulta | ✔ (sin eliminar) |
| Clientes | ✔ | ✔ (sin eliminar) | |
| Ventas | ✔ (incluye anular) | ✔ (sin anular) | |
| Movimientos de inventario | ✔ | | ✔ |
| Reportes | ✔ | dashboard | dashboard e inventario |

### Endpoints

Cada módulo CRUD sigue el mismo patrón que CitasMedicas:

| Verbo | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/{modulo}` | Listar todos |
| `GET` | `/api/{modulo}/{id}` | Obtener uno |
| `GET` | `/api/{modulo}/filter/{offset}/{limit}?filtros` | Paginación (límite 1–100) y filtros |
| `POST` | `/api/{modulo}` | Crear |
| `PUT` | `/api/{modulo}/{id}` | Actualizar (parcial) |
| `DELETE` | `/api/{modulo}/{id}` | Eliminar |

Módulos: `usuarios`, `categorias`, `proveedores`, `productos`, `clientes`, `ventas`, `movimientos` (este último sin `PUT`/`DELETE`: es una bitácora).

Rutas adicionales:

| Verbo | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/auth/login` | Inicia sesión y devuelve el JWT |
| `GET` | `/api/auth/perfil` | Datos del usuario del token |
| `GET` | `/api/roles` | Catálogo de roles |
| `GET` | `/api/productos/codigo/{codigo}` | Buscar por código de barras (escáner de la app móvil) |
| `POST` | `/api/ventas/cotizar` | Calcula la venta (precios, descuento, IVA, total) sin guardarla |
| `DELETE` | `/api/ventas/{id}` | **Anula** la venta (no la borra) y devuelve el stock |
| `GET` | `/api/reportes/dashboard` | Indicadores clave |
| `GET` | `/api/reportes/ventas-periodo` | Ventas por día o mes (`agrupar=dia\|mes`) |
| `GET` | `/api/reportes/productos-mas-vendidos` | Top de productos (`limite`) |
| `GET` | `/api/reportes/ventas-categoria` | Ingresos por categoría y % de participación |
| `GET` | `/api/reportes/ventas-vendedor` | Desempeño por vendedor |
| `GET` | `/api/reportes/ventas-metodo-pago` | Ventas por método de pago |
| `GET` | `/api/reportes/inventario` | Stock bajo y valorización del inventario |
| `GET` | `/api/docs` | Swagger UI |

Los reportes aceptan `desde` y `hasta` (`AAAA-MM-DD`; por defecto los últimos 30 días) y `formato=csv` para descargar el archivo.

### Respuestas

```jsonc
// Éxito
{ "data": { ... } }
// Listado paginado
{ "data": [ ... ], "pagination": { "offset": 0, "limit": 10, "total": 24 } }
// Error
{ "error": "Producto no encontrado." }
// Validación
{ "errores": { "precio_venta": "El precio de venta no puede ser menor que el precio de compra." } }
```

| Código | Cuándo |
|---|---|
| 200 / 201 | Consulta o actualización correcta / recurso creado |
| 400 | El cuerpo no es un JSON válido |
| 401 | Token inválido, vencido o ausente; credenciales incorrectas |
| 403 | El rol no tiene permiso |
| 404 | Recurso o ruta no encontrada |
| 405 | Método no permitido en la ruta |
| 409 | Duplicado, registros asociados o stock insuficiente al guardar |
| 422 | Datos inválidos |
| 502 | La capa de datos no responde |

### Reglas de negocio principales

- El precio de cada línea de venta lo toma el servidor del catálogo: el cliente solo envía `producto_id` y `cantidad`.
- IVA del 13 % sobre el monto con descuento; descuento máximo del 50 %. Los montos se calculan en céntimos para evitar errores de redondeo.
- No se vende un producto inactivo ni más unidades de las disponibles. La capa de datos vuelve a verificar el stock dentro de la transacción, con bloqueo de filas (`lockForUpdate`).
- El stock no se edita directamente: cambia solo por ventas, anulaciones o movimientos, y todo queda en la bitácora.
- El precio de venta no puede ser menor que el de compra.
- No se eliminan productos ya vendidos ni usuarios con ventas: se desactivan (`activo = false`).
- Un administrador no puede eliminarse, desactivarse ni quitarse el rol a sí mismo.

---

## 4. Estructura

```
InventarioVentas
├── docker-compose.yml
├── db_password.txt
├── db_dump/                       ← se ejecutan en orden al crear el volumen
│   ├── 01_esquema.sql
│   ├── 02_procedimientos.sql
│   └── 03_datos.sql
├── Data/                          ← capa de datos (puerto 8080)
│   ├── Dockerfile, composer.json, .env
│   ├── RESTRequest/data.http
│   ├── public/ (.htaccess, index.php)
│   └── src/
│       ├── app/        App.php, config.php, conexion.php, eloquent.php, routes.php, errores.php
│       ├── Controllers/ BaseController + un controlador por módulo + ReporteController
│       └── Models/      modelos Eloquent
└── Business/                      ← capa de negocio (puerto 9090)
    ├── Dockerfile, composer.json, phpunit.xml, .env
    ├── docs/openapi.yaml          ← especificación Swagger / OpenAPI 3
    ├── RESTRequest/*.http
    ├── public/ (.htaccess, index.php)
    ├── src/
    │   ├── app/         App.php (JWT + CORS), config.php, routes.php (roles), errores.php
    │   ├── Controllers/ reciben la petición y responden
    │   ├── Services/    reglas de negocio + DataService (cliente HTTP hacia Data)
    │   ├── Validators/  reglas de respect/validation
    │   ├── Middleware/  RoleMiddleware (autorización por rol)
    │   └── Exceptions/  ApiException, ValidacionException, ServicioNoDisponibleException
    └── tests/           pruebas unitarias (PHPUnit)
```

---

## 5. Diferencias con CitasMedicas

Se mantuvo la misma estructura (Data/Business, Slim, PHP-DI, Eloquent, Guzzle, `respect/validation`, `jimtools/jwt-auth`, mismos Dockerfiles y archivos de arranque). Lo que cambió y por qué:

| Cambio | Motivo |
|---|---|
| Composer se copia de la imagen oficial (`COPY --from=composer:2`) | El instalador con hash sha384 fijo deja de funcionar cuando Composer publica un instalador nuevo. |
| `KEY` del JWT de más de 32 caracteres | `firebase/php-jwt` 7 rechaza claves HS256 de menos de 256 bits. |
| Manejo de errores centralizado (`errores.php`) en ambas capas | Requisito del enunciado; los controladores ya no repiten `try/catch`. |
| Capa de servicios en Business (`Services/`) | Requisito de arquitectura en capas y de pruebas unitarias en servicios de negocio. |
| `DataServiceInterface` | Permite probar los servicios sin la capa de datos. |
| `RoleMiddleware` | Autorización por roles (requisito de seguridad). |
| Swagger UI servido por la API (`/api/docs`) | Documentación navegable de la API. |
| `.env` y `db_password.txt` versionados | Para que el proyecto levante con `docker compose up -d` recién clonado (credenciales solo de desarrollo). |
| Healthcheck de MySQL y zona horaria `America/Costa_Rica` | La capa de datos espera a la base de datos y las fechas de los reportes coinciden con la hora local. |

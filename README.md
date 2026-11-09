# API de la Veterinaria Itapebí (PHP puro + MySQL + JWT)

Migración del sistema de historia clínica (antes PHP "espagueti" con formularios) a una API REST,
con la misma estructura que la API de productos y categorías:
**Router → Middleware → Controladores → Modelos**, validadores aparte, un único punto de entrada
y autenticación con JWT (access token + refresh token con rotación) y roles.

Usa **las mismas tablas y columnas de la base actual** (`itapebi`), así que no hay que migrar datos:
solo se agregan las tablas de autenticación y la columna de rol de los usuarios.

Requisitos: **PHP 8.0 o superior** con `pdo_mysql` (y opcionalmente `mbstring` y `fileinfo`), MySQL 8
y **Composer** solo para el PDF (dompdf).

## Estructura

```
veterinaria-api/
├── public/
│   ├── index.php              # único punto de entrada
│   └── .htaccess              # solo para Apache
├── bootstrap.php              # autoloader (PSR-4) + .env + zona horaria + vendor (dompdf)
├── routes/
│   └── api.php                # rutas y middleware de cada una
├── src/
│   ├── Core/                  # Router, Request, Response, Database, Env, Jwt, Storage (archivos)
│   ├── Controllers/           # Auth, Usuario, Especie, Propietario, Mascota, Consulta, Vacuna, Cuota
│   ├── Models/                # uno por tabla + RefreshToken y LoginIntento
│   ├── Validators/            # reglas de validación y limpieza de datos
│   ├── Middleware/            # Cors, AuthMiddleware, RoleMiddleware
│   └── Reports/               # ConsultaPdf (resumen de historia clínica)
├── storage/consultas/         # archivos adjuntos de las consultas (fuera de public/)
├── resources/img/             # logo del PDF
├── bin/
│   └── crear-usuario.php      # crea o reactiva un usuario / resetea su contraseña
├── migrations/
│   └── 001_auth.sql           # para la base que ya está en uso
├── schema.sql                 # esquema completo para una instalación nueva
└── .env.example
```

## Qué reemplaza a qué

| Sistema anterior | API nueva |
|---|---|
| `login.php`, `logout.php` | `POST /auth/login`, `POST /auth/logout` |
| `especies.php`, `frmAgregarEspecie.php`, `agregarEspecie.php`, ... | `/especies` |
| `propietarios.php`, `agregarPropietario.php`, ... | `/propietarios` |
| `mascotas.php`, `agregarMascota.php`, ... (+ `obtenerEdad()`) | `/mascotas` (devuelve `edad` y `edad_texto`) |
| `consultas.php`, `agregarConsulta.php` (con archivos), `frmDetalleConsulta.php`, ... | `/consultas` y `/consultas/{id}/archivos/{1-3}` |
| `reportePDF.php` | `GET /consultas/{id}/pdf` |
| `vacunas.php`, `agregarVacuna.php`, ... | `/vacunas` |
| `vencimientos.php` | `GET /vacunas/vencimientos` |
| `cuotas.php`, `agregarCuota.php`, ... | `/cuotas` |
| `eliminarX.php` (ponía Estado = 0) | `DELETE /x/{id}` (también baja lógica, solo admin) |

## Instalación

### Usar la base actual (lo normal)

1. **Respaldo.** Antes de tocar nada, exportá la base (con HeidiSQL o `mysqldump`).
2. **Migración** (agrega `UsuarioRol` a `usuario` y las tablas `refresh_tokens` y `login_intentos`; no toca datos clínicos):
   ```
   mysql -u root -p itapebi < migrations/001_auth.sql
   ```
   Los usuarios que ya existían quedan como **admin**. Su contraseña sigue siendo la misma.
3. **Configuración.** Copiá `.env.example` como `.env`, completá `DB_*` y generá el secreto JWT:
   ```
   copy .env.example .env
   php -r "echo bin2hex(random_bytes(32));"
   ```
   Pegá el resultado en `JWT_SECRET=` (sin ese valor las rutas protegidas devuelven error 500).
4. **dompdf** (solo para el PDF): `composer install`
5. **Archivos de las consultas.** Copiá el contenido de la carpeta `archivos/` del sistema viejo a
   `storage/consultas/`. La API los encuentra por el nombre que ya está guardado en la base.
6. **Levantar el servidor:**
   ```
   php -S localhost:8000 -t public
   ```
7. Probá: http://localhost:8000/ (muestra la lista de rutas)

### Instalación nueva (sin datos)

`mysql -u root -p < schema.sql`, después los pasos 3, 4 y 6, y creá el primer usuario:
```
php bin/crear-usuario.php "Mariana Zammit" mariana admin
```

`bin/crear-usuario.php` también sirve si alguien olvida su contraseña: con un usuario que ya existe,
te pide una nueva, lo reactiva y le pone el rol indicado.

## Permisos

Son datos clínicos y personales, así que **todo requiere token** salvo `/auth/login`, `/auth/refresh` y `/auth/logout`.

| Acción | Quién puede |
|--------|-------------|
| `GET`, `POST`, `PUT` de especies, propietarios, mascotas, consultas, vacunas y cuotas | Cualquier usuario autenticado |
| Subir, ver y borrar archivos de consultas; ver el PDF | Cualquier usuario autenticado |
| `DELETE` (bajas) de especies, propietarios, mascotas, consultas, vacunas y cuotas | Solo `admin` |
| `/usuarios` (alta, edición, reset de contraseña, baja) | Solo `admin` |

No hay registro público. Se cambian en una línea en `routes/api.php`.

## Endpoints

### Autenticación

| Método | Ruta | Body | Descripción |
|--------|------|------|-------------|
| POST | `/auth/login` | `usuario`, `password` | Devuelve el usuario y los tokens |
| POST | `/auth/refresh` | `refresh_token` | Canjea el refresh token por un par nuevo |
| POST | `/auth/logout` | `refresh_token` | Revoca el refresh token |
| GET | `/auth/me` | - | Datos del usuario autenticado |
| PUT | `/auth/password` | `password_actual`, `password_nueva` | Cambia la contraseña propia y cierra las otras sesiones |

### Recursos

Todos los listados paginados aceptan `page` (default 1) y `per_page` (default 20, máx. 100), y responden
`meta.pagination` con `page`, `per_page`, `total` y `total_pages`.

| Recurso | Filtros del listado | Body de POST/PUT |
|---|---|---|
| `/especies` | (sin paginar) | `nombre` |
| `/propietarios` | `search` (nombre, apellido, teléfono, email) | `nombre`, `apellido`, `telefono`, `direccion`, `email` |
| `/mascotas` | `especie_id`, `propietario_id`, `search` | `nombre`, `especie_id`, `propietario_id`, `fecha_nacimiento` |
| `/consultas` | `mascota_id`, `desde`, `hasta`, `search` | `fecha`, `mascota_id`, `motivo`, `diagnostico`, `tratamiento` |
| `/vacunas` | `mascota_id`, `search` | `fecha_ingreso`, `mascota_id`, `fecha_vencimiento` |
| `/cuotas` | `mascota_id`, `desde`, `hasta`, `search` | `fecha`, `mascota_id`, `valor` (pesos enteros), `observacion` |
| `/usuarios` | (sin paginar) | `nombre`, `usuario`, `password`, `rol` |

Cada recurso tiene `GET /x`, `GET /x/{id}`, `POST /x`, `PUT /x/{id}` y `DELETE /x/{id}`.
Las fechas van siempre como `AAAA-MM-DD`. Extras:

- `GET /propietarios/{id}/mascotas`: las mascotas de un propietario.
- `GET /cuotas` devuelve también `meta.suma_valor`: el total de todas las cuotas que cumplen los filtros.
- No se puede borrar un propietario ni una especie que tengan mascotas activas (responde 409).

Ejemplo de mascota en las respuestas:
```json
{
  "id": 25, "nombre": "Cora", "fecha_nacimiento": "2010-01-23", "edad": 16, "edad_texto": "16 años",
  "especie_id": 5, "especie_nombre": "Canino",
  "propietario_id": 6, "propietario_nombre": "Luis", "propietario_apellido": "Geymonat", "propietario_telefono": "094054281",
  "creado_en": "2026-10-08 13:10:42", "modificado_en": null
}
```

### Archivos de las consultas

Cada consulta tiene hasta 3 archivos (números 1, 2 y 3). Se suben aparte, después de crear la consulta:

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/consultas/{id}/archivos/{numero}` | `multipart/form-data` con el campo `archivo`. Si ya había uno en ese número, lo reemplaza |
| GET | `/consultas/{id}/archivos/{numero}` | Devuelve el archivo (agregá `?descargar=1` para forzar la descarga) |
| DELETE | `/consultas/{id}/archivos/{numero}` | Quita el archivo |

Se aceptan PDF, JPG, PNG, WEBP y GIF, hasta `UPLOAD_MAX_MB` (10 MB por defecto). Se controla el contenido
real del archivo, no solo la extensión, y se guarda con un nombre generado (el nombre original no llega al disco).
Revisá también `upload_max_filesize` y `post_max_size` en `php.ini`.

### PDF y vencimientos

- `GET /consultas/{id}/pdf`: resumen de la consulta (agregá `?descargar=1` para descargarlo).
- `GET /vacunas/vencimientos`: parámetros `filtro` = `todas` (default), `vencidas` o `proximas`;
  `dias` (para `proximas`, default 30); `historico=1` para ver todas las vacunas.
  Por defecto muestra **solo la última vacuna de cada mascota activa**: si una mascota ya se revacunó,
  la vacuna anterior no aparece como vencida. Cada ítem trae `vencida` y `dias_para_vencer`.

**Desde el frontend:** como todo pide token, los archivos y el PDF no se abren con un `<a href>` común.
Pedilos con `fetch` (con la cabecera `Authorization`) y abrí el resultado:
```js
const r = await fetch(`${API}/consultas/12/pdf`, { headers: { Authorization: `Bearer ${token}` } });
window.open(URL.createObjectURL(await r.blob()));
```

## Pruebas rápidas con curl (CMD de Windows)

```
curl -X POST http://localhost:8000/auth/login -H "Content-Type: application/json" -d "{\"usuario\":\"mariana\",\"password\":\"su-clave\"}"

curl http://localhost:8000/mascotas?search=cora -H "Authorization: Bearer EL_ACCESS_TOKEN"

curl -X POST http://localhost:8000/consultas -H "Content-Type: application/json" -H "Authorization: Bearer EL_ACCESS_TOKEN" -d "{\"fecha\":\"2026-11-06\",\"mascota_id\":25,\"motivo\":\"Control anual\"}"

curl -X POST http://localhost:8000/consultas/13/archivos/1 -H "Authorization: Bearer EL_ACCESS_TOKEN" -F "archivo=@C:\ruta\estudio.pdf"

curl "http://localhost:8000/vacunas/vencimientos?filtro=proximas&dias=15" -H "Authorization: Bearer EL_ACCESS_TOKEN"
```

## Formato de respuestas

Éxito: `{ "success": true, "message": "...", "data": {}, "meta": {} }`
Error: `{ "success": false, "message": "...", "errors": ["..."] }`

Códigos usados: 200, 201, 400 (JSON inválido), 401 (sin token, token inválido o credenciales incorrectas),
403 (sin permisos), 404, 405, 409 (duplicado o en uso), 413 (archivo demasiado grande), 422 (validación),
429 (demasiados intentos de login), 500.

## Cambios de seguridad respecto del sistema anterior

- `login.php` armaba la consulta SQL concatenando el usuario y la clave (inyección SQL). Ahora todo usa consultas preparadas con PDO.
- Las contraseñas con SHA-1 que puedan quedar se aceptan una vez y se pasan a bcrypt en ese mismo login.
- Las pantallas verificaban la sesión (en `templates/header.php`), pero los scripts que guardan, editan o borran (`agregarX.php`, `editarX.php`, `eliminarX.php`, `reportePDF.php`) no: se podían llamar sin iniciar sesión. Ahora cada ruta exige un token válido.
- Los archivos subidos se guardaban en una carpeta pública con su nombre original (se podían pisar y subir `.php`). Ahora van a `storage/`, fuera de `public/`, con nombre generado y tipo verificado.
- El PDF y los listados escapan el texto (antes se imprimía tal cual).
- Las credenciales de la base estaban en `conexion.php` dentro del repositorio: ahora van en `.env` (está en `.gitignore`).
- Login con límite de 5 intentos fallidos por usuario + IP cada 15 minutos.

## Producción

- Usá **HTTPS**: los tokens viajan en cada petición.
- `APP_DEBUG=false` y `CORS_ALLOWED_ORIGIN` con el dominio real del frontend.
- En Apache, apuntá el DocumentRoot a `public/` (el `.htaccess` redirige todo a `index.php` y pasa la cabecera `Authorization`). Si el hosting no permite cambiar el DocumentRoot, subí la API a una subcarpeta y definí `APP_BASE_PATH`; en ese caso asegurate de que `storage/`, `src/` y `.env` no queden accesibles desde la web.
- `storage/consultas/` necesita permiso de escritura para PHP, y hay que incluirla en los respaldos.
- Podés borrar periódicamente los refresh tokens vencidos o revocados de la tabla `refresh_tokens`.

## Cómo agregar un nuevo recurso

1. Modelo en `src/Models/` (extiende `Model`), validador en `src/Validators/` y controlador en `src/Controllers/` (extiende `Controller`).
2. Rutas en `routes/api.php`, con `$auth` o `$admin` según corresponda.

# Vortex — Auth & Health API

Spec mínima de auth + health. Resto de endpoints en `vortex-data-api.md`.

## Base URL

| Entorno | URL |
|---|---|
| Emulador Android | `http://10.0.2.2:8000` |
| Device en LAN | `http://<host LAN IP>:8000` |
| Producción | _TBD_ |

## Headers

```
Accept: application/json                  # siempre
Content-Type: application/json            # si hay body
Authorization: Bearer <token>             # endpoints autenticados
```

## Auth model

- Bearer tokens Sanctum, formato `<id>|<random>`. Sin expiración hasta logout.
- Stateless. Token por device (logout solo revoca el token usado).
- 422 en credenciales mal (no 401, para shape uniforme).

## Errores comunes

| Status | Significado |
|---|---|
| `401` | Token ausente / inválido / revocado → limpiar token, ir a login |
| `422` | Validación falló (incluye login mal). Body: `{message, errors:{field:[...]}}` |
| `429` | Throttle (mostrar "esperá un momento") |
| `503` | (`/up`) backend up pero DB caída |

---

## `GET /up` — health check

Público. Sin body.

**200**:
```json
{
  "status": "ok",
  "checks": { "app": "ok", "database": { "status": "ok", "connection": "pgsql" } },
  "timestamp": "2026-05-09T20:39:50+00:00"
}
```

**503**: mismo shape con `status: "degraded"` y `database.status: "error"`.

---

## `POST /api/register`

Público. Throttle 60/hora (dev).

**Body**:
```json
{ "name": "Juan", "email": "juan@x.com", "password": "min8chars",
  "password_confirmation": "min8chars", "role": "athlete" }
```

| Campo | Reglas |
|---|---|
| `name` | string 2–100 |
| `email` | email válido, único, max 255 |
| `password` | string min 8 (Laravel `Password::defaults()`) |
| `password_confirmation` | igual a `password` |
| `role` | `"athlete"` o `"instructor"` |

**201**:
```json
{ "token": "11|abc...", "user": { "id":10, "name":"Juan", "email":"juan@x.com", "role":"athlete", "created_at":"..." } }
```

---

## `POST /api/login`

Público. Throttle 20/min (dev).

**Body**: `{ "email": "...", "password": "..." }`

**200**: mismo shape que register.

**Credenciales mal** → **422** con `errors.email: ["Las credenciales no coinciden."]` (no distinguir email vs password).

---

## `POST /api/logout`

Auth required. Sin body.

**204** sin body. Revoca solo el token de esta request.

---

## `GET /api/user`

Auth required. Sin body.

**200**: User raw (con `role_id` numérico + timestamps). Shape inestable — usar como probe de "token válido" más que parsear.

```json
{ "id":10, "email":"...", "name":"...", "role_id":1, "created_at":"...", "updated_at":"...", "deleted_at":null }
```

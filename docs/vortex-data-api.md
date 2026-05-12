# Vortex — Data API

Endpoints de datos: profile, MVC, sesiones, sets, guests + claim. Auth + health en `vortex-auth-api.md`.

**Estado**: Fase 6 (instructor guests + claim codes + autorización dual en endpoints de sesión).

## Reglas comunes

- Todos requieren `Authorization: Bearer <token>`.
- **Rol athlete**: `/api/athlete/*`, `GET/POST /api/sessions`, `POST /api/claim`.
- **Rol instructor**: `/api/instructor/*`.
- **Dual-role** (athlete dueño O instructor de sesión guest): `GET/PATCH /api/sessions/{id}`, `PUT /api/sessions/{id}/end`, `POST /api/sessions/{id}/sets`.
- Resources individuales devuelven el objeto plano. Solo los listados paginados llevan `{data, links, meta}`.

| Status | Cuándo |
|---|---|
| `401` | Sin token / revocado |
| `403` | Rol no autorizado |
| `404` | Recurso ajeno o inexistente |
| `409` | Sesión ya cerrada (set ingestion) |
| `410` | Claim code expirado o usado |
| `422` | Validación. Body: `{message, errors:{field:[...]}}` |
| `429` | Throttle (solo `/api/claim`) |

---

## Athlete profile

### `GET /api/athlete/profile`

Sin body. Devuelve la fila de `athlete_profiles` del usuario autenticado. **404** si todavía no se creó.

### `POST /api/athlete/profile`

**Body**:
```json
{
  "first_name": "Juan",
  "last_name": "Pérez",
  "bodyweight_kg": 82.5,
  "age_years": 28,
  "sex": "MALE"
}
```

| Campo | Reglas |
|---|---|
| `first_name` / `last_name` | string 1–100 |
| `bodyweight_kg` | number 30–300 |
| `age_years` | int 14–100 |
| `sex` | `MALE` \| `FEMALE` |

**DB**: INSERT en `athlete_profiles` con `user_id = auth()->id()`. **422** si ya existe (1:1 con user).

### `PATCH /api/athlete/profile`

Body = cualquier subset de los campos del POST (mismas reglas). **DB**: UPDATE parcial sobre `athlete_profiles`. Body vacío es no-op (200).

---

## MVC calibrations

### `POST /api/athlete/mvc`

**Body**:
```json
{
  "calibrations": [
    { "muscle": "VASTUS_LATERALIS", "side": "LEFT",  "mvc_value": 88.4 },
    { "muscle": "VASTUS_LATERALIS", "side": "RIGHT", "mvc_value": 86.1 }
  ]
}
```

| Campo | Reglas |
|---|---|
| `calibrations` | array, min 1 |
| `muscle` | `VASTUS_LATERALIS`, `VASTUS_MEDIALIS`, `GLUTEUS_MAXIMUS`, `ERECTOR_SPINAE`, `BICEPS_FEMORIS` |
| `side` | `LEFT` \| `RIGHT` |
| `mvc_value` | number en `(0, 100]` (%MVC normalizado en device) |

**DB** (transacción):
- UPSERT 1 fila en `mvc_calibrations` keyed por `athlete_profile_id`, mapeando cada `(muscle, side)` a una de las 10 columnas wide (`vastus_lateralis_left`, ...). Slots no enviados quedan en NULL.
- UPDATE `athlete_profiles.calibrated_at = now()`.

**422** si el atleta aún no creó su perfil.

---

## Sesiones de entrenamiento

`training_session` ≠ sesión de auth. Una "sesión" es un conjunto de series de squats. Una serie es un conjunto de repeticiones del ejercicio.

### `GET /api/sessions` *(athlete-only)*

Query: `?page=N`. Paginado DESC por `started_at`, 15/pág. **DB**: SELECT `WHERE athlete_user_id = auth()->id()`.

### `POST /api/sessions` *(athlete-only)*

**Body**:
```json
{
  "started_at": "2026-05-10T15:30:00Z",
  "exercise": "back_squat",
  "device_source": "SIMULATED"
}
```

| Campo | Reglas |
|---|---|
| `started_at` | ISO datetime, requerido |
| `exercise` | string 1–50, opcional (default `back_squat`) |
| `device_source` | `REAL` \| `SIMULATED`, opcional (default `SIMULATED`) |

**DB**: INSERT en `training_sessions` con `athlete_user_id = auth()->id()`, `guest_profile_id = null`, `instructor_user_id = null`. **201**.

### `GET /api/sessions/{id}` *(dual-role)*

Sin body. Devuelve la sesión + nested completo: `sets[]` (cada uno con `reps[]` con activations inline, `metrics{}`, `recommendations[]`). Sesión vacía → `sets: []`. **404** si no es del athlete dueño ni del instructor de la sesión guest.

### `PATCH /api/sessions/{id}` *(dual-role)*

**Body**:
```json
{
  "device_source": "REAL"
}
```

Único campo editable, requerido.

**DB**: UPDATE `training_sessions.device_source`.

### `PUT /api/sessions/{id}/end` *(dual-role)*

**Body**:
```json
{
  "ended_at": "2026-05-10T16:05:00Z"
}
```

Requerido, ISO datetime.

**DB**: UPDATE `training_sessions.ended_at`. Idempotente — re-llamar sobrescribe.

---

## Set ingestion ⭐

### `POST /api/sessions/{id}/sets` *(dual-role)*

Núcleo del sistema. Una llamada persiste el set entero en transacción.

**Reglas previas**:
- Sesión debe pertenecer al athlete autenticado **o** ser una sesión guest del instructor autenticado → si no, **404**.
- Sesión cerrada (`ended_at != null`) → **409**.
- Idempotencia por `(session_id, set_number)`: retry con mismo `set_number` → **200** con el set existente, body ignorado.

**Body**:
```json
{
  "set_number":1, "load_kg":140.5, "target_reps":5,
  "variant":"LOW_BAR", "depth":"PARALLEL", "rpe":8.5,
  "reps":[
    { "rep_number":1, "duration_ms":2400,
      "activations":[
        { "muscle":"VASTUS_LATERALIS", "side":"LEFT", "percent_mvc":87.2, "peak_percent_mvc":112.4 }
      ]
    }
  ],
  "metrics":{
    "bsa_vl_pct":32.5, "bsa_vm_pct":28.5, "bsa_gmax_pct":25.0, "bsa_es_pct":14.0,
    "hq_ratio":0.45, "es_gmax_ratio":0.62, "intra_set_fatigue_ratio":0.18, "thresholds_version":1
  },
  "recommendations":[
    { "text":"...", "severity":"MONITOR", "evidence":"es_gmax_ratio=0.62" }
  ]
}
```

| Campo | Reglas |
|---|---|
| `set_number` | int 1–99 (único en sesión → clave idempotencia) |
| `load_kg` | number 0–9999.99 |
| `target_reps` | int 1–50 |
| `variant` | `LOW_BAR` \| `HIGH_BAR` |
| `depth` | `ABOVE_PARALLEL` \| `PARALLEL` \| `BELOW_PARALLEL` |
| `rpe` | number 1.0–10.0 |
| `reps` | array 1–50 |
| `reps.*.rep_number` | int 1–50, único en set |
| `reps.*.duration_ms` | int 0–600000, opcional |
| `reps.*.activations` | array 0–10 (vacío OK — electrodo suelto) |
| `activations.*.muscle` / `side` | enums; combinación única por rep |
| `activations.*.percent_mvc` / `peak_percent_mvc` | number en `(0, 300]` |
| `metrics.bsa_*_pct` | number `[0, 100]` |
| `metrics.hq_ratio`, `es_gmax_ratio` | number `> 0` |
| `metrics.intra_set_fatigue_ratio` | number `>= 0` |
| `metrics.thresholds_version` | int `>= 1`, opcional (default 1) |
| `recommendations.*.text` | string 1–500 |
| `recommendations.*.severity` | `NORMAL` \| `MONITOR` \| `RISK` |
| `recommendations.*.evidence` | string max 500, opcional |

**DB** (todo en una transacción):
- INSERT en `training_sets`.
- Por cada rep, INSERT en `reps`: las `activations[]` se mapean a las 20 columnas wide (`vastus_lateralis_left_pct`, `..._peak_pct`, etc.). Slots no enviados → NULL.
- INSERT en `set_metrics` (1:1 con set).
- INSERT 0..N filas en `recommendations`.

**201** (nuevo) / **200** (retry): set completo con todo el nested.

> Cap `(0, 300]` y no `(0, 100]`: el MVC test isométrico subestima el máximo dinámico real, así que peaks >100% son comunes y legítimos en ejercicio.

---

## Instructor — guests + claim codes

Flujo: el instructor crea un guest "en frío" (sin cuenta), lo calibra, le hace sesiones, le entrega un código de 8 chars. El atleta lo canjea al darse de alta y hereda perfil + calibración + sesiones.

### `POST /api/instructor/guests`

Body idéntico a `POST /api/athlete/profile` (`first_name`, `last_name`, `bodyweight_kg`, `age_years`, `sex`).

**DB**: INSERT en `guest_profiles` con `created_by_user_id = auth()->id()`, `claimed_by_user_id = null`. **201**.

### `GET /api/instructor/guests`

Sin body. Paginado DESC por `created_at`, 20/pág. **DB**: SELECT `WHERE created_by_user_id = auth()->id()`.

### `POST /api/instructor/guests/{id}/mvc`

Body idéntico a `POST /api/athlete/mvc`.

**DB**: UPSERT 1 fila en `mvc_calibrations` keyed por `guest_profile_id` + UPDATE `guest_profiles.calibrated_at`. **404** si el guest no es del instructor.

### `POST /api/instructor/sessions`

**Body**:
```json
{
  "guest_profile_id": 7,
  "started_at": "2026-05-11T15:30:00Z",
  "exercise": "back_squat",
  "device_source": "SIMULATED"
}
```

| Campo | Reglas |
|---|---|
| `guest_profile_id` | int, requerido, existe en `guest_profiles` |
| `started_at` | ISO datetime, requerido |
| `exercise` / `device_source` | mismos defaults que `POST /api/sessions` |

**DB**: INSERT en `training_sessions` con `athlete_user_id = null`, `guest_profile_id = <body>`, `instructor_user_id = auth()->id()`. **201**.
**404** si el guest no es del instructor. **422** si el guest ya está reclamado.

### `POST /api/instructor/sessions/{id}/claim-code`

Sin body.

**DB** (transacción):
- UPDATE en `claim_codes` activos previos para esa sesión → `expires_at = now()` (invalidación).
- INSERT nueva fila en `claim_codes` con `code = <8 chars>` (alfabeto sin `0/O/1/I/L`), `expires_at = now() + 5min`.

**201** → `{ code, session_id, expires_at }`. **404** si la sesión no es del instructor o no tiene `guest_profile_id`.

### `POST /api/claim` *(athlete-only)*

**Body**:
```json
{
  "code": "K9P2XM4R"
}
```

8 chars, case-insensitive — server lo normaliza a uppercase.

Throttle: 10 intentos / 5 min por usuario.

**DB** (transacción, con `lockForUpdate` sobre code, session y guest):
1. Si el athlete **no** tiene `athlete_profiles`: INSERT con los datos del guest + INSERT en `mvc_calibrations` copiando los valores del guest.
2. UPDATE `training_sessions`: `athlete_user_id = auth()->id()`, `guest_profile_id = null` (la sesión pasa al athlete; el `instructor_user_id` se mantiene).
3. UPDATE `guest_profiles`: `claimed_by_user_id = auth()->id()`, `claimed_at = now()`.
4. INSERT en `instructor_athlete` (si no existía ya el par).
5. UPDATE `claim_codes`: `used_at = now()`, `used_by_user_id = auth()->id()`.

**200** → SessionResource de la sesión transferida.

| Status | Cuándo |
|---|---|
| `404` | Código no existe |
| `410` | Código expirado o ya usado |
| `422` | Body inválido (`code` ausente / longitud distinta a 8) |
| `403` | Instructor intenta canjear |
| `429` | Throttle |

> Si el athlete ya tenía profile + calibración, **no se sobrescriben** — solo se transfiere la sesión. El `guest_profiles` row se conserva (auditoría), solo cambian `claimed_*`.

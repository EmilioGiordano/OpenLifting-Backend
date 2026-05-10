# OpenLifting — Contexto para el backend Laravel

## Qué es esto

Sistema de análisis de activación muscular (sEMG) para powerlifting. Atletas usan sensores EMG + ESP32 durante sentadillas; una app Android captura y procesa los datos. Este backend es el **sync target**: la app computa todo localmente (offline-first) y sincroniza al backend vía REST. El backend no recomputa métricas — solo almacena lo que la app envía.

## Stack

- **Framework**: Laravel (PHP), proyecto en `backend-openlifting/`
- **DB**: PostgreSQL
- **Auth**: Laravel Sanctum (tokens de API, no sesiones web)
- **Tests**: PHPUnit
- **Sin starter kit** — solo API JSON, sin Blade/Breeze/Jetstream

## Proyecto Laravel

Ruta: `C:\Users\giord\Desktop\Facultad\2026-last-run\2026-finales\Computacion-movil\OpenLifting-backend\backend-openlifting\`

Comandos útiles:
```bash
php artisan serve          # dev en localhost:8000
php artisan migrate
php artisan make:model Name -m
php artisan make:controller Api/NameController --api
php artisan test
```

---

## Schema de base de datos

### `users`
```sql
id              BIGSERIAL PK
email           VARCHAR(255) UNIQUE NOT NULL
name            VARCHAR(255) NOT NULL
password        VARCHAR(255) NOT NULL          -- bcrypt
role            VARCHAR(20) NOT NULL           -- 'athlete' | 'instructor'
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `athlete_profiles`
```sql
id              BIGSERIAL PK
user_id         BIGINT FK → users (CASCADE DELETE)
first_name      VARCHAR(100) NOT NULL
last_name       VARCHAR(100) NOT NULL
bodyweight_kg   DECIMAL(5,2) NOT NULL
age_years       SMALLINT NOT NULL
sex             VARCHAR(10) NOT NULL           -- 'male' | 'female' | 'other'
calibrated_at   TIMESTAMP NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `mvc_calibrations`
Un valor vigente por músculo+lado por atleta (upsert al recalibrar).
```sql
id                  BIGSERIAL PK
athlete_profile_id  BIGINT FK → athlete_profiles (CASCADE DELETE)
muscle              VARCHAR(30) NOT NULL       -- ver enum Muscle abajo
side                VARCHAR(5) NOT NULL        -- 'LEFT' | 'RIGHT'
mvc_value           FLOAT NOT NULL             -- RMS baseline usado como 100%
recorded_at         TIMESTAMP NOT NULL
UNIQUE(athlete_profile_id, muscle, side)
```

### `instructor_athlete` (pivot)
```sql
instructor_id   BIGINT FK → users
athlete_id      BIGINT FK → users
linked_at       TIMESTAMP NOT NULL DEFAULT now()
PRIMARY KEY (instructor_id, athlete_id)
```

### `training_sessions`
```sql
id                  BIGSERIAL PK
athlete_user_id     BIGINT FK → users NOT NULL
instructor_user_id  BIGINT FK → users NULL
exercise            VARCHAR(50) NOT NULL DEFAULT 'back_squat'
started_at          TIMESTAMP NOT NULL
ended_at            TIMESTAMP NULL
device_source       VARCHAR(20) NOT NULL DEFAULT 'SIMULATED'  -- 'REAL' | 'SIMULATED'
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

### `training_sets`
```sql
id          BIGSERIAL PK
session_id  BIGINT FK → training_sessions (CASCADE DELETE)
set_number  SMALLINT NOT NULL
load_kg     DECIMAL(6,2) NOT NULL
target_reps SMALLINT NOT NULL
variant     VARCHAR(20) NOT NULL    -- 'LOW_BAR' | 'HIGH_BAR'
depth       VARCHAR(20) NOT NULL    -- 'ABOVE_PARALLEL' | 'PARALLEL' | 'BELOW_PARALLEL'
rpe         DECIMAL(3,1) NOT NULL   -- 1.0–10.0 en pasos de 0.5
created_at  TIMESTAMP
```

### `reps`
```sql
id          BIGSERIAL PK
set_id      BIGINT FK → training_sets (CASCADE DELETE)
rep_number  SMALLINT NOT NULL
duration_ms INT NOT NULL DEFAULT 0
```

### `muscle_activations`
```sql
id              BIGSERIAL PK
rep_id          BIGINT FK → reps (CASCADE DELETE)
muscle          VARCHAR(30) NOT NULL
side            VARCHAR(5) NOT NULL    -- 'LEFT' | 'RIGHT'
percent_mvc     FLOAT NOT NULL
peak_percent_mvc FLOAT NOT NULL
```

### `set_metrics`
Un registro por set (1:1). La app manda los valores ya computados.
```sql
id                      BIGSERIAL PK
set_id                  BIGINT FK → training_sets (CASCADE DELETE) UNIQUE
bsa_vl_pct              FLOAT NOT NULL
bsa_vm_pct              FLOAT NOT NULL
bsa_gmax_pct            FLOAT NOT NULL
bsa_es_pct              FLOAT NOT NULL
hq_ratio                FLOAT NOT NULL
es_gmax_ratio           FLOAT NOT NULL
intra_set_fatigue_ratio FLOAT NOT NULL
thresholds_version      SMALLINT NOT NULL DEFAULT 1
```

### `recommendations`
```sql
id          BIGSERIAL PK
set_id      BIGINT FK → training_sets (CASCADE DELETE)
text        TEXT NOT NULL
severity    VARCHAR(10) NOT NULL   -- 'NORMAL' | 'MONITOR' | 'RISK'
evidence    TEXT NULL
```

---

## Enums (valores de string en la DB)

**Muscle:** `VASTUS_LATERALIS`, `VASTUS_MEDIALIS`, `GLUTEUS_MAXIMUS`, `ERECTOR_SPINAE`, `BICEPS_FEMORIS`

**MuscleSide:** `LEFT`, `RIGHT`

**SquatVariant:** `LOW_BAR`, `HIGH_BAR`

**SquatDepth:** `ABOVE_PARALLEL`, `PARALLEL`, `BELOW_PARALLEL`

**RiskLevel:** `NORMAL`, `MONITOR`, `RISK`

**UserRole:** `athlete`, `instructor`

---

## APIs necesarias (mínimas para el MVP)

Todas bajo prefijo `/api/`. Auth via `Authorization: Bearer {token}` (Sanctum).

### Auth (sin autenticación previa)
```
POST /api/register          body: {name, email, password, role}
POST /api/login             body: {email, password}  → {token, user}
POST /api/logout            (requiere token)
```

### Perfil de atleta
```
GET  /api/athlete/profile           → AthleteProfile
POST /api/athlete/profile           body: {first_name, last_name, bodyweight_kg, age_years, sex}
PUT  /api/athlete/profile           body: campos a actualizar
POST /api/athlete/mvc               body: [{muscle, side, mvc_value}]  → upsert calibraciones
```

### Sesiones
```
GET  /api/sessions                  → lista paginada de sesiones del atleta autenticado
POST /api/sessions                  body: {exercise, started_at, device_source}  → {id}
PUT  /api/sessions/{id}/end         body: {ended_at}
```

### Series (sync completo en un POST)
La app manda todo de una vez después de completar una serie:
```
POST /api/sessions/{session_id}/sets
body: {
  set_number, load_kg, target_reps, variant, depth, rpe,
  reps: [
    {
      rep_number, duration_ms,
      activations: [{muscle, side, percent_mvc, peak_percent_mvc}]
    }
  ],
  metrics: {bsa_vl_pct, bsa_vm_pct, bsa_gmax_pct, bsa_es_pct,
            hq_ratio, es_gmax_ratio, intra_set_fatigue_ratio, thresholds_version},
  recommendations: [{text, severity, evidence}]
}
→ {set_id}
```

### Relación instructor–atleta
```
GET  /api/instructor/athletes       → lista de atletas vinculados
POST /api/instructor/athletes       body: {athlete_email}  → vincula por email
GET  /api/instructor/athletes/{id}/sessions   → sesiones del atleta
```

---

## Decisiones clave

1. **Offline-first**: la app guarda en Room (SQLite local) primero; el backend es sync target. El campo `synced` en las entidades de la app indica si ya se sincronizó.
2. **Métricas computadas por la app**: el backend no recomputa BSA, H:Q, ES:GMax ni fatiga. Solo almacena lo que recibe.
3. **Un POST por set**: para simplificar la sync, toda la información de una serie (reps + activaciones + métricas + recomendaciones) se envía en una sola request.
4. **Roles separados**: un usuario es `athlete` o `instructor`. Los instructores pueden ver sesiones de atletas vinculados.
5. **Sin recomputation backend**: si en el futuro se cambian los thresholds, el campo `thresholds_version` permite identificar con qué versión se computó cada set.
6. **Sin Google OAuth por ahora**: auth simple con email/password + Sanctum. Extensible a OAuth después.
7. **Escala EMG: `%MVC` en `[0, 100]`, normalizado en el dispositivo**: tanto `mvc_calibrations.mvc_value` como (a futuro) `muscle_activations.percent_mvc` viajan ya normalizados desde el firmware/simulador. El backend nunca ve μV crudos; almacena lo que recibe y enforce `0 < value <= 100` vía CHECK constraint. Decisión completa con alternativas y consecuencias en `docs/adr/0001-emg-data-scale.md`.

---

## Estructura de carpetas Laravel sugerida

```
app/
  Http/
    Controllers/
      Api/
        AuthController.php
        AthleteProfileController.php
        SessionController.php
        SetController.php
        InstructorController.php
    Requests/          ← Form Requests para validación
    Resources/         ← API Resources para serialización JSON
  Models/
    User.php, AthleteProfile.php, MvcCalibration.php,
    TrainingSession.php, TrainingSet.php, Rep.php,
    MuscleActivation.php, SetMetrics.php, Recommendation.php,
    InstructorAthlete.php (pivot)
database/
  migrations/          ← una migración por tabla, en orden de dependencias
routes/
  api.php              ← todas las rutas aquí
```

## Orden sugerido de migraciones

1. users
2. athlete_profiles
3. mvc_calibrations
4. instructor_athlete
5. training_sessions
6. training_sets
7. reps
8. muscle_activations
9. set_metrics
10. recommendations

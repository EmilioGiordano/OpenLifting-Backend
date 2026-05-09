-- OpenLifting — PostgreSQL Schema
-- Orden: respeta dependencias FK

-- ─────────────────────────────────────────────
-- 1. ROLES
-- ─────────────────────────────────────────────
CREATE TABLE roles (
    id         SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name       VARCHAR(20)  NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT now(),
    updated_at TIMESTAMP             DEFAULT now(),
    deleted_at TIMESTAMP             DEFAULT NULL
);

-- ─────────────────────────────────────────────
-- 2. USERS
-- ─────────────────────────────────────────────
CREATE TABLE users (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    name       VARCHAR(100) NOT NULL,
    password   VARCHAR(255) NOT NULL,
    role_id    SMALLINT     NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT now(),
    updated_at TIMESTAMP             DEFAULT now(),
    deleted_at TIMESTAMP             DEFAULT NULL,

    CONSTRAINT fk_users_role  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    CONSTRAINT uq_users_email UNIQUE (email)
);

-- ─────────────────────────────────────────────
-- 3. ATHLETE PROFILES (1:1 con users)
-- ─────────────────────────────────────────────
CREATE TABLE athlete_profiles (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id       BIGINT       NOT NULL,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    bodyweight_kg DECIMAL(5,2) NOT NULL,
    age_years     SMALLINT     NOT NULL,
    sex           VARCHAR(10)  NOT NULL,
    calibrated_at TIMESTAMP    NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT now(),
    updated_at    TIMESTAMP             DEFAULT now(),
    deleted_at    TIMESTAMP             DEFAULT NULL,

    CONSTRAINT fk_athlete_profiles_user    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT uq_athlete_profiles_user_id UNIQUE (user_id),
    CONSTRAINT chk_athlete_profiles_sex    CHECK (sex IN ('MALE', 'FEMALE'))
);

-- ─────────────────────────────────────────────
-- 4. MVC CALIBRATIONS
-- Un valor vigente por músculo+lado (upsert al recalibrar)
-- ─────────────────────────────────────────────
CREATE TABLE mvc_calibrations (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    athlete_profile_id BIGINT      NOT NULL,
    muscle             VARCHAR(30) NOT NULL,
    side               VARCHAR(5)  NOT NULL,
    mvc_value          FLOAT       NOT NULL,
    recorded_at        TIMESTAMP   NOT NULL DEFAULT now(),
    deleted_at         TIMESTAMP            DEFAULT NULL,

    CONSTRAINT fk_mvc_calibrations_profile FOREIGN KEY (athlete_profile_id) REFERENCES athlete_profiles(id) ON DELETE RESTRICT,
    CONSTRAINT uq_mvc_calibrations_slot    UNIQUE (athlete_profile_id, muscle, side),
    CONSTRAINT chk_mvc_calibrations_muscle CHECK (muscle IN ('VASTUS_LATERALIS', 'VASTUS_MEDIALIS', 'GLUTEUS_MAXIMUS', 'ERECTOR_SPINAE', 'BICEPS_FEMORIS')),
    CONSTRAINT chk_mvc_calibrations_side   CHECK (side IN ('LEFT', 'RIGHT'))
);

-- ─────────────────────────────────────────────
-- 5. INSTRUCTOR ↔ ATHLETE (pivot)
-- ─────────────────────────────────────────────
CREATE TABLE instructor_athlete (
    instructor_id BIGINT    NOT NULL,
    athlete_id    BIGINT    NOT NULL,
    linked_at     TIMESTAMP NOT NULL DEFAULT now(),
    deleted_at    TIMESTAMP          DEFAULT NULL,

    CONSTRAINT pk_instructor_athlete            PRIMARY KEY (instructor_id, athlete_id),
    CONSTRAINT fk_instructor_athlete_instructor FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_instructor_athlete_athlete    FOREIGN KEY (athlete_id)    REFERENCES users(id) ON DELETE RESTRICT
);

-- ─────────────────────────────────────────────
-- 6. TRAINING SESSIONS
-- ─────────────────────────────────────────────
CREATE TABLE training_sessions (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    athlete_user_id    BIGINT      NOT NULL,
    instructor_user_id BIGINT      NULL,
    exercise           VARCHAR(50) NOT NULL DEFAULT 'back_squat',
    started_at         TIMESTAMP   NOT NULL,
    ended_at           TIMESTAMP   NULL,
    device_source      VARCHAR(20) NOT NULL DEFAULT 'SIMULATED',
    created_at         TIMESTAMP   NOT NULL DEFAULT now(),
    updated_at         TIMESTAMP            DEFAULT now(),
    deleted_at         TIMESTAMP            DEFAULT NULL,

    CONSTRAINT fk_training_sessions_athlete    FOREIGN KEY (athlete_user_id)    REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_training_sessions_instructor FOREIGN KEY (instructor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_training_sessions_source    CHECK (device_source IN ('REAL SENSORS', 'SIMULATED'))
);

-- ─────────────────────────────────────────────
-- 7. TRAINING SETS
-- ─────────────────────────────────────────────
CREATE TABLE training_sets (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    session_id  BIGINT       NOT NULL,
    set_number  SMALLINT     NOT NULL,
    load_kg     DECIMAL(6,2) NOT NULL,
    target_reps SMALLINT     NOT NULL,
    variant     VARCHAR(20)  NOT NULL,
    depth       VARCHAR(20)  NOT NULL,
    rpe         DECIMAL(3,1) NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT now(),
    deleted_at  TIMESTAMP             DEFAULT NULL,

    CONSTRAINT fk_training_sets_session  FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE RESTRICT,
    CONSTRAINT chk_training_sets_variant CHECK (variant IN ('LOW_BAR', 'HIGH_BAR')),
    CONSTRAINT chk_training_sets_depth   CHECK (depth IN ('ABOVE_PARALLEL', 'PARALLEL', 'BELOW_PARALLEL')),
    CONSTRAINT chk_training_sets_rpe     CHECK (rpe BETWEEN 1.0 AND 10.0)
);

-- ─────────────────────────────────────────────
-- 8. REPS
-- ─────────────────────────────────────────────
CREATE TABLE reps (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    set_id      BIGINT   NOT NULL,
    rep_number  SMALLINT NOT NULL,
    duration_ms INT      NOT NULL DEFAULT 0,
    deleted_at  TIMESTAMP         DEFAULT NULL,

    CONSTRAINT fk_reps_set FOREIGN KEY (set_id) REFERENCES training_sets(id) ON DELETE RESTRICT
);

-- ─────────────────────────────────────────────
-- 9. MUSCLE ACTIVATIONS (por rep, músculo y lado)
-- ─────────────────────────────────────────────
CREATE TABLE muscle_activations (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    rep_id           BIGINT      NOT NULL,
    muscle           VARCHAR(30) NOT NULL,
    side             VARCHAR(5)  NOT NULL,
    percent_mvc      FLOAT       NOT NULL,
    peak_percent_mvc FLOAT       NOT NULL,
    deleted_at       TIMESTAMP            DEFAULT NULL,

    CONSTRAINT fk_muscle_activations_rep     FOREIGN KEY (rep_id) REFERENCES reps(id) ON DELETE RESTRICT,
    CONSTRAINT chk_muscle_activations_muscle CHECK (muscle IN ('VASTUS_LATERALIS', 'VASTUS_MEDIALIS', 'GLUTEUS_MAXIMUS', 'ERECTOR_SPINAE', 'BICEPS_FEMORIS')),
    CONSTRAINT chk_muscle_activations_side   CHECK (side IN ('LEFT', 'RIGHT'))
);

-- ─────────────────────────────────────────────
-- 10. SET METRICS (1:1 con training_sets)
-- La app manda los valores ya computados, el backend solo almacena.
-- ─────────────────────────────────────────────
CREATE TABLE set_metrics (
    id                      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    set_id                  BIGINT   NOT NULL,
    bsa_vl_pct              FLOAT    NOT NULL,
    bsa_vm_pct              FLOAT    NOT NULL,
    bsa_gmax_pct            FLOAT    NOT NULL,
    bsa_es_pct              FLOAT    NOT NULL,
    hq_ratio                FLOAT    NOT NULL,
    es_gmax_ratio           FLOAT    NOT NULL,
    intra_set_fatigue_ratio FLOAT    NOT NULL,
    thresholds_version      SMALLINT NOT NULL DEFAULT 1,
    deleted_at              TIMESTAMP         DEFAULT NULL,

    CONSTRAINT fk_set_metrics_set FOREIGN KEY (set_id) REFERENCES training_sets(id) ON DELETE RESTRICT,
    CONSTRAINT uq_set_metrics_set UNIQUE (set_id)
);

-- ─────────────────────────────────────────────
-- 11. RECOMMENDATIONS
-- ─────────────────────────────────────────────
CREATE TABLE recommendations (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    set_id     BIGINT      NOT NULL,
    text       TEXT        NOT NULL,
    severity   VARCHAR(10) NOT NULL,
    evidence   TEXT        NULL,
    deleted_at TIMESTAMP            DEFAULT NULL,

    CONSTRAINT fk_recommendations_set       FOREIGN KEY (set_id) REFERENCES training_sets(id) ON DELETE RESTRICT,
    CONSTRAINT chk_recommendations_severity CHECK (severity IN ('NORMAL', 'MONITOR', 'RISK'))
);

-- ─────────────────────────────────────────────
-- ÍNDICES en columnas FK
-- PostgreSQL no los crea automáticamente en la tabla referenciante
-- ─────────────────────────────────────────────
CREATE INDEX idx_athlete_profiles_user_id      ON athlete_profiles(user_id);
CREATE INDEX idx_mvc_calibrations_profile_id   ON mvc_calibrations(athlete_profile_id);
CREATE INDEX idx_instructor_athlete_athlete_id ON instructor_athlete(athlete_id);
CREATE INDEX idx_training_sessions_athlete     ON training_sessions(athlete_user_id);
CREATE INDEX idx_training_sessions_instructor  ON training_sessions(instructor_user_id);
CREATE INDEX idx_training_sets_session_id      ON training_sets(session_id);
CREATE INDEX idx_reps_set_id                   ON reps(set_id);
CREATE INDEX idx_muscle_activations_rep_id     ON muscle_activations(rep_id);
CREATE INDEX idx_recommendations_set_id        ON recommendations(set_id);
-- set_metrics.set_id ya tiene índice implícito por UNIQUE

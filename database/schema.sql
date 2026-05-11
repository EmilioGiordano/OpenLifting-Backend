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

CREATE INDEX idx_users_role_id ON users(role_id);

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

    CONSTRAINT fk_athlete_profiles_user        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT uq_athlete_profiles_user_id     UNIQUE (user_id),
    CONSTRAINT chk_athlete_profiles_sex        CHECK (sex IN ('MALE', 'FEMALE')),
    CONSTRAINT chk_athlete_profiles_bodyweight CHECK (bodyweight_kg BETWEEN 30 AND 300),
    CONSTRAINT chk_athlete_profiles_age        CHECK (age_years BETWEEN 14 AND 100)
);

-- user_id ya tiene índice implícito por UNIQUE

-- ─────────────────────────────────────────────
-- 4. GUEST PROFILES
-- Atleta sin cuenta creado por un instructor para registrar una sesión en frío.
-- Al reclamar (POST /api/claim con un claim_code), claimed_by_user_id se setea
-- al user_id del atleta que se hizo cuenta, y los datos de calibración +
-- la sesión asociada se transfieren al athlete_profile real (los registros
-- del guest se conservan para auditoría).
-- ─────────────────────────────────────────────
CREATE TABLE guest_profiles (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    created_by_user_id BIGINT       NOT NULL,
    claimed_by_user_id BIGINT           NULL,
    first_name         VARCHAR(100) NOT NULL,
    last_name          VARCHAR(100) NOT NULL,
    bodyweight_kg      DECIMAL(5,2) NOT NULL,
    age_years          SMALLINT     NOT NULL,
    sex                VARCHAR(10)  NOT NULL,
    calibrated_at      TIMESTAMP        NULL,
    claimed_at         TIMESTAMP        NULL,
    created_at         TIMESTAMP    NOT NULL DEFAULT now(),
    updated_at         TIMESTAMP             DEFAULT now(),
    deleted_at         TIMESTAMP             DEFAULT NULL,

    CONSTRAINT fk_guest_profiles_creator     FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_guest_profiles_claimer     FOREIGN KEY (claimed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_guest_profiles_sex        CHECK (sex IN ('MALE', 'FEMALE')),
    CONSTRAINT chk_guest_profiles_bodyweight CHECK (bodyweight_kg BETWEEN 30 AND 300),
    CONSTRAINT chk_guest_profiles_age        CHECK (age_years BETWEEN 14 AND 100),
    -- claimed_at va de la mano con claimed_by_user_id: ambos NULL o ambos NOT NULL
    CONSTRAINT chk_guest_profiles_claim_pair CHECK (
        (claimed_by_user_id IS NULL AND claimed_at IS NULL)
        OR
        (claimed_by_user_id IS NOT NULL AND claimed_at IS NOT NULL)
    )
);

CREATE INDEX idx_guest_profiles_creator ON guest_profiles(created_by_user_id);
CREATE INDEX idx_guest_profiles_claimer ON guest_profiles(claimed_by_user_id);

-- ─────────────────────────────────────────────
-- 5. MVC CALIBRATIONS
-- 1 fila por sujeto (athlete_profile O guest_profile, XOR). Layout wide:
-- 10 columnas %MVC, una por slot (5 músculos × 2 lados). NULL = ese slot no
-- se calibró todavía. Valores en (0, 100] — el MVC es el peak normalizado del
-- test isométrico por definición. Ver ADR-0001.
-- ─────────────────────────────────────────────
CREATE TABLE mvc_calibrations (
    id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    athlete_profile_id       BIGINT     NULL,
    guest_profile_id         BIGINT     NULL,

    vastus_lateralis_left    FLOAT      NULL,
    vastus_lateralis_right   FLOAT      NULL,
    vastus_medialis_left     FLOAT      NULL,
    vastus_medialis_right    FLOAT      NULL,
    gluteus_maximus_left     FLOAT      NULL,
    gluteus_maximus_right    FLOAT      NULL,
    erector_spinae_left      FLOAT      NULL,
    erector_spinae_right     FLOAT      NULL,
    biceps_femoris_left      FLOAT      NULL,
    biceps_femoris_right     FLOAT      NULL,

    recorded_at              TIMESTAMP  NOT NULL DEFAULT now(),
    deleted_at               TIMESTAMP           DEFAULT NULL,

    CONSTRAINT fk_mvc_calibrations_athlete FOREIGN KEY (athlete_profile_id) REFERENCES athlete_profiles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mvc_calibrations_guest   FOREIGN KEY (guest_profile_id)   REFERENCES guest_profiles(id)   ON DELETE RESTRICT,
    -- XOR: exactamente uno de los dos profiles tiene que estar seteado
    CONSTRAINT chk_mvc_calibrations_owner  CHECK (
        (athlete_profile_id IS NOT NULL)::int + (guest_profile_id IS NOT NULL)::int = 1
    ),
    -- 10 CHECK constraints, uno por columna. NULL pasa automáticamente (3VL).
    CONSTRAINT chk_mvc_vastus_lateralis_left  CHECK (vastus_lateralis_left  > 0 AND vastus_lateralis_left  <= 100),
    CONSTRAINT chk_mvc_vastus_lateralis_right CHECK (vastus_lateralis_right > 0 AND vastus_lateralis_right <= 100),
    CONSTRAINT chk_mvc_vastus_medialis_left   CHECK (vastus_medialis_left   > 0 AND vastus_medialis_left   <= 100),
    CONSTRAINT chk_mvc_vastus_medialis_right  CHECK (vastus_medialis_right  > 0 AND vastus_medialis_right  <= 100),
    CONSTRAINT chk_mvc_gluteus_maximus_left   CHECK (gluteus_maximus_left   > 0 AND gluteus_maximus_left   <= 100),
    CONSTRAINT chk_mvc_gluteus_maximus_right  CHECK (gluteus_maximus_right  > 0 AND gluteus_maximus_right  <= 100),
    CONSTRAINT chk_mvc_erector_spinae_left    CHECK (erector_spinae_left    > 0 AND erector_spinae_left    <= 100),
    CONSTRAINT chk_mvc_erector_spinae_right   CHECK (erector_spinae_right   > 0 AND erector_spinae_right   <= 100),
    CONSTRAINT chk_mvc_biceps_femoris_left    CHECK (biceps_femoris_left    > 0 AND biceps_femoris_left    <= 100),
    CONSTRAINT chk_mvc_biceps_femoris_right   CHECK (biceps_femoris_right   > 0 AND biceps_femoris_right   <= 100)
);

-- 1:1 con el profile (XOR enforced arriba). Partial unique indexes porque la
-- columna del otro tipo está NULL y un UNIQUE compuesto trataría a los NULL
-- como distintos (permitiría duplicados del lado contrario).
CREATE UNIQUE INDEX uq_mvc_calibrations_athlete
    ON mvc_calibrations (athlete_profile_id)
    WHERE athlete_profile_id IS NOT NULL;

CREATE UNIQUE INDEX uq_mvc_calibrations_guest
    ON mvc_calibrations (guest_profile_id)
    WHERE guest_profile_id IS NOT NULL;

-- ─────────────────────────────────────────────
-- 6. INSTRUCTOR ↔ ATHLETE (pivot)
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

CREATE INDEX idx_instructor_athlete_athlete_id ON instructor_athlete(athlete_id);
-- instructor_id ya tiene índice implícito por ser primera columna del PK compuesto

-- ─────────────────────────────────────────────
-- 7. TRAINING SESSIONS
-- El sujeto de la sesión es un athlete (athlete_user_id) O un guest
-- (guest_profile_id) — XOR enforced por CHECK. Al reclamar un guest, la fila
-- pivota: athlete_user_id pasa a estar seteado y guest_profile_id se nulea.
-- instructor_user_id queda NOT NULL cuando un instructor levantó la sesión
-- en nombre del sujeto, NULL cuando el athlete se auto-registró.
-- ─────────────────────────────────────────────
CREATE TABLE training_sessions (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    athlete_user_id    BIGINT      NULL,
    guest_profile_id   BIGINT      NULL,
    instructor_user_id BIGINT      NULL,
    exercise           VARCHAR(50) NOT NULL DEFAULT 'back_squat',
    started_at         TIMESTAMP   NOT NULL,
    ended_at           TIMESTAMP   NULL,
    device_source      VARCHAR(20) NOT NULL DEFAULT 'SIMULATED',
    created_at         TIMESTAMP   NOT NULL DEFAULT now(),
    updated_at         TIMESTAMP            DEFAULT now(),
    deleted_at         TIMESTAMP            DEFAULT NULL,

    CONSTRAINT fk_training_sessions_athlete    FOREIGN KEY (athlete_user_id)    REFERENCES users(id)           ON DELETE RESTRICT,
    CONSTRAINT fk_training_sessions_guest      FOREIGN KEY (guest_profile_id)   REFERENCES guest_profiles(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_training_sessions_instructor FOREIGN KEY (instructor_user_id) REFERENCES users(id)           ON DELETE SET NULL,
    CONSTRAINT chk_training_sessions_source    CHECK (device_source IN ('REAL', 'SIMULATED')),
    -- XOR: exactamente uno de los dos sujetos
    CONSTRAINT chk_training_sessions_subject   CHECK (
        (athlete_user_id IS NOT NULL)::int + (guest_profile_id IS NOT NULL)::int = 1
    )
);

CREATE INDEX idx_training_sessions_athlete    ON training_sessions(athlete_user_id);
CREATE INDEX idx_training_sessions_guest      ON training_sessions(guest_profile_id);
CREATE INDEX idx_training_sessions_instructor ON training_sessions(instructor_user_id);

-- ─────────────────────────────────────────────
-- 8. TRAINING SETS
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

    CONSTRAINT fk_training_sets_session    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE RESTRICT,
    CONSTRAINT uq_training_sets_set_number UNIQUE (session_id, set_number),
    CONSTRAINT chk_training_sets_variant   CHECK (variant IN ('LOW_BAR', 'HIGH_BAR')),
    CONSTRAINT chk_training_sets_depth     CHECK (depth IN ('ABOVE_PARALLEL', 'PARALLEL', 'BELOW_PARALLEL')),
    CONSTRAINT chk_training_sets_rpe       CHECK (rpe BETWEEN 1.0 AND 10.0)
);

-- session_id ya tiene índice implícito por ser primera columna del UNIQUE compuesto

-- ─────────────────────────────────────────────
-- 9. REPS (incluye 20 columnas de muscle activations: 5 músculos × 2 lados × {avg, peak})
-- Diseño wide en lugar de una tabla muscle_activations 1:N porque el schema de slots
-- es cerrado (5 músculos × 2 lados, fijo por alcance de tesis), siempre se leen juntos
-- y nunca se filtra por músculo individual. NULL = electrodo suelto / no medido.
-- (0, 300] permite picos > 100 reales en ejercicio dinámico (el MVC test isométrico
-- subestima el max), pero rechaza outliers groseros. Ver ADR-0001.
-- ─────────────────────────────────────────────
CREATE TABLE reps (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    set_id      BIGINT   NOT NULL,
    rep_number  SMALLINT NOT NULL,
    duration_ms INT      NOT NULL DEFAULT 0,

    -- Muscle activations: 10 slots × {avg, peak} = 20 columnas, todas nullable
    vastus_lateralis_left_pct       FLOAT NULL, vastus_lateralis_left_peak_pct  FLOAT NULL,
    vastus_lateralis_right_pct      FLOAT NULL, vastus_lateralis_right_peak_pct FLOAT NULL,
    vastus_medialis_left_pct        FLOAT NULL, vastus_medialis_left_peak_pct   FLOAT NULL,
    vastus_medialis_right_pct       FLOAT NULL, vastus_medialis_right_peak_pct  FLOAT NULL,
    gluteus_maximus_left_pct        FLOAT NULL, gluteus_maximus_left_peak_pct   FLOAT NULL,
    gluteus_maximus_right_pct       FLOAT NULL, gluteus_maximus_right_peak_pct  FLOAT NULL,
    erector_spinae_left_pct         FLOAT NULL, erector_spinae_left_peak_pct    FLOAT NULL,
    erector_spinae_right_pct        FLOAT NULL, erector_spinae_right_peak_pct   FLOAT NULL,
    biceps_femoris_left_pct         FLOAT NULL, biceps_femoris_left_peak_pct    FLOAT NULL,
    biceps_femoris_right_pct        FLOAT NULL, biceps_femoris_right_peak_pct   FLOAT NULL,

    deleted_at  TIMESTAMP         DEFAULT NULL,

    CONSTRAINT fk_reps_set        FOREIGN KEY (set_id) REFERENCES training_sets(id) ON DELETE RESTRICT,
    CONSTRAINT uq_reps_rep_number UNIQUE (set_id, rep_number),

    -- 20 CHECK constraints, uno por columna. NULL pasa automáticamente (3VL).
    CONSTRAINT chk_reps_vastus_lateralis_left_pct       CHECK (vastus_lateralis_left_pct       > 0 AND vastus_lateralis_left_pct       <= 300),
    CONSTRAINT chk_reps_vastus_lateralis_left_peak_pct  CHECK (vastus_lateralis_left_peak_pct  > 0 AND vastus_lateralis_left_peak_pct  <= 300),
    CONSTRAINT chk_reps_vastus_lateralis_right_pct      CHECK (vastus_lateralis_right_pct      > 0 AND vastus_lateralis_right_pct      <= 300),
    CONSTRAINT chk_reps_vastus_lateralis_right_peak_pct CHECK (vastus_lateralis_right_peak_pct > 0 AND vastus_lateralis_right_peak_pct <= 300),
    CONSTRAINT chk_reps_vastus_medialis_left_pct        CHECK (vastus_medialis_left_pct        > 0 AND vastus_medialis_left_pct        <= 300),
    CONSTRAINT chk_reps_vastus_medialis_left_peak_pct   CHECK (vastus_medialis_left_peak_pct   > 0 AND vastus_medialis_left_peak_pct   <= 300),
    CONSTRAINT chk_reps_vastus_medialis_right_pct       CHECK (vastus_medialis_right_pct       > 0 AND vastus_medialis_right_pct       <= 300),
    CONSTRAINT chk_reps_vastus_medialis_right_peak_pct  CHECK (vastus_medialis_right_peak_pct  > 0 AND vastus_medialis_right_peak_pct  <= 300),
    CONSTRAINT chk_reps_gluteus_maximus_left_pct        CHECK (gluteus_maximus_left_pct        > 0 AND gluteus_maximus_left_pct        <= 300),
    CONSTRAINT chk_reps_gluteus_maximus_left_peak_pct   CHECK (gluteus_maximus_left_peak_pct   > 0 AND gluteus_maximus_left_peak_pct   <= 300),
    CONSTRAINT chk_reps_gluteus_maximus_right_pct       CHECK (gluteus_maximus_right_pct       > 0 AND gluteus_maximus_right_pct       <= 300),
    CONSTRAINT chk_reps_gluteus_maximus_right_peak_pct  CHECK (gluteus_maximus_right_peak_pct  > 0 AND gluteus_maximus_right_peak_pct  <= 300),
    CONSTRAINT chk_reps_erector_spinae_left_pct         CHECK (erector_spinae_left_pct         > 0 AND erector_spinae_left_pct         <= 300),
    CONSTRAINT chk_reps_erector_spinae_left_peak_pct    CHECK (erector_spinae_left_peak_pct    > 0 AND erector_spinae_left_peak_pct    <= 300),
    CONSTRAINT chk_reps_erector_spinae_right_pct        CHECK (erector_spinae_right_pct        > 0 AND erector_spinae_right_pct        <= 300),
    CONSTRAINT chk_reps_erector_spinae_right_peak_pct   CHECK (erector_spinae_right_peak_pct   > 0 AND erector_spinae_right_peak_pct   <= 300),
    CONSTRAINT chk_reps_biceps_femoris_left_pct         CHECK (biceps_femoris_left_pct         > 0 AND biceps_femoris_left_pct         <= 300),
    CONSTRAINT chk_reps_biceps_femoris_left_peak_pct    CHECK (biceps_femoris_left_peak_pct    > 0 AND biceps_femoris_left_peak_pct    <= 300),
    CONSTRAINT chk_reps_biceps_femoris_right_pct        CHECK (biceps_femoris_right_pct        > 0 AND biceps_femoris_right_pct        <= 300),
    CONSTRAINT chk_reps_biceps_femoris_right_peak_pct   CHECK (biceps_femoris_right_peak_pct   > 0 AND biceps_femoris_right_peak_pct   <= 300)
);

-- set_id ya tiene índice implícito por ser primera columna del UNIQUE compuesto

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

-- set_id ya tiene índice implícito por UNIQUE

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

CREATE INDEX idx_recommendations_set_id ON recommendations(set_id);

-- ─────────────────────────────────────────────
-- 12. CLAIM CODES
-- Códigos de un solo uso que un instructor genera para una sesión guest, y que
-- un atleta canjea vía POST /api/claim para tomar posesión de los datos.
-- "Código activo" = used_at IS NULL AND expires_at > now(). La regla "solo
-- un código activo por sesión" se aplica en la capa de aplicación (al generar
-- uno nuevo, los anteriores activos quedan con expires_at = now()).
-- ─────────────────────────────────────────────
CREATE TABLE claim_codes (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    session_id      BIGINT      NOT NULL,
    code            VARCHAR(8)  NOT NULL,
    expires_at      TIMESTAMP   NOT NULL,
    used_at         TIMESTAMP   NULL,
    used_by_user_id BIGINT      NULL,
    created_at      TIMESTAMP   NOT NULL DEFAULT now(),
    deleted_at      TIMESTAMP            DEFAULT NULL,

    CONSTRAINT fk_claim_codes_session FOREIGN KEY (session_id)      REFERENCES training_sessions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_claim_codes_user    FOREIGN KEY (used_by_user_id) REFERENCES users(id)             ON DELETE RESTRICT,
    CONSTRAINT uq_claim_codes_code    UNIQUE (code),
    -- used_at y used_by_user_id van de la mano: ambos NULL o ambos NOT NULL
    CONSTRAINT chk_claim_codes_used_pair CHECK (
        (used_at IS NULL AND used_by_user_id IS NULL)
        OR
        (used_at IS NOT NULL AND used_by_user_id IS NOT NULL)
    )
);

CREATE INDEX idx_claim_codes_session ON claim_codes(session_id);

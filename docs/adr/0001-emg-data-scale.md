# ADR-0001 — EMG data scale: %MVC normalized at the device, not raw RMS

- **Status:** Accepted
- **Date:** 2026-05-10
- **Components affected:** firmware (ESP32), Android app (OpenLifting-Mobile), backend (OpenLifting / Vortex)
- **Stakeholders:** backend + mobile dev sessions

---

## Context

OpenLifting captures surface EMG signals from five muscles × two sides during back squat sets, normalizes them against each athlete's own MVC (Maximum Voluntary Contraction) baseline, and persists the normalized values for analysis and recommendations.

Two values are involved:

- `mvc_calibrations.mvc_value` — the reference baseline captured during a dedicated calibration test.
- `muscle_activations.percent_mvc` (and `peak_percent_mvc`) — per-rep activation expressed against that baseline.

For both values to be useful, **they must be in compatible units**. The wire format and storage units have to be agreed across all three components (firmware, mobile, backend).

The early backend implementation enforced only `mvc_value > 0` with no upper bound and no documented unit. As a result, two scales coexisted in the database during early development:

- Smoke-test data inserted by the backend dev loop: values in `[0, 1]` (e.g. `0.83`, `0.91`).
- First real captures from the Android simulator: values in `[72, 99]` (e.g. `84.6`, `93.4`).

Both scales passed the existing constraint, but they're not interchangeable: any analysis that mixes them would produce nonsense, and there was no normative answer to "what is `mvc_value` actually measuring".

This ADR records the resolution.

## Decision

**EMG values cross the wire as `%MVC` in the closed range `[0, 100]`. Normalization happens at the device (firmware in production, simulator in development), not at the backend.**

Concretely:

1. **`mvc_calibrations.mvc_value`** is the **peak `%MVC` observed during the calibration test** for a given (athlete, muscle, side). Storage range: `(0, 100]`.

   - A perfect MVC test yields `100`. In practice, neural inhibition during voluntary maximal contractions makes athletes typically peak in `[70, 99]`; the simulator's `Esp32Simulator.captureMvc()` reflects this, drawing values from a Gaussian centered at 89 and clamped to `[72, 99]`.
   - `mvc_value` is **not** a baseline in microvolts that the backend or app divides into. It is a quality-of-capture proxy: how close to 100% did the athlete get on the test? In production it acts as a reference timestamp for how stale the calibration is, not as a divisor.

2. **`muscle_activations.percent_mvc`** and **`muscle_activations.peak_percent_mvc`** (Phase 5) follow the **same `[0, 100]` scale**. The firmware (or simulator) computes them locally as `(rep_rms_uv / mvc_baseline_uv) * 100` and emits the result. The wire never carries microvolts.

3. **The backend stores what it receives, validates the range, and never recomputes.** This is consistent with the broader architectural principle (see `OpenLifting-backend/CLAUDE.md`): the backend is a sync target for client-side metrics, not a compute engine.

## Pipeline (end-to-end)

```
[ EMG sensors ]
      │
      │  raw analog
      ▼
[ ESP32 firmware ]  ──── computes RMS over a window
      │
      │  rms_uv (microvolts, internal only)
      │
      │  during calibration:        peak_rms_uv → emit as `peak %MVC` (always 100 by definition,
      │                                                     except for capture artifacts)
      │  during a rep:              rms_uv      → divide by stored mvc_baseline_uv → emit as `%MVC`
      ▼
[ Wire format / Bluetooth ]
      │
      │  values in [0, 100]
      ▼
[ Android app ]  ──── packages set + reps + activations + metrics + recommendations
      │
      │  POST /api/sessions/{id}/sets       ← Phase 5
      │  POST /api/athlete/mvc              ← Phase 3 (already shipped)
      ▼
[ Vortex backend ]  ──── stores as-is, enforces 0 < value ≤ 100, never recomputes
```

The microvolt values exist only inside the firmware. Everything from the firmware boundary outward is `%MVC`.

## Alternatives considered

### A. Raw microvolts on the wire, normalization at the app

Initial backend proposal. Rejected because:

- It requires the app to cache `mvc_baseline_uv` per athlete locally and apply the division on every reading — duplicates the firmware's work.
- It makes `mvc_value` non-comparable across athletes (each athlete's μV scale depends on electrode placement, skin impedance, sensor batch).
- It breaks the principle "the firmware computes what the firmware can compute". Sensor calibration drift, baseline correction, and normalization windowing all need to live near the hardware.
- The current simulator already produces `%MVC` directly — adopting μV would force a refactor with no functional gain.

### B. Normalized in `[0, 1]` instead of `[0, 100]`

Rejected because:

- Cosmetic: equivalent up to a factor of 100, but loses two digits of legibility for human-facing dashboards.
- Confusing in error messages, logs, and instructor reports (`mvc_value: 0.88` requires a tooltip every time).
- Inconsistent with how clinical EMG literature reports `%MVC` (always `0–100`).

### C. No upper bound (status quo until this ADR)

Rejected because it enabled the dual-scale incident that prompted this ADR. Without a bound, the database accepts any positive number, and there is no fence against a misconfigured client emitting μV by mistake.

## Consequences

### Backend

- `mvc_calibrations` gains a CHECK constraint: `mvc_value > 0 AND mvc_value <= 100`.
- `StoreMvcCalibrationsRequest` adds `max:100` alongside `gt:0` for fast 422 feedback before hitting the DB.
- `MvcCalibrationFactory` is updated to draw from `[70, 99]`, matching realistic captures.
- Test data inserted before this ADR (the `[0, 1]` smoke-test rows) is wiped via `migrate:fresh`. Any future seed data must use the `[0, 100]` scale.
- When Phase 5 lands, `muscle_activations.percent_mvc` and `peak_percent_mvc` will inherit the same constraint structure. A small amount of headroom above 100 may be allowed there to absorb the case where an athlete contracts harder during a rep than during their calibration test (common: MVC tests undershoot true max because of neural inhibition); the ceiling will be set when Phase 5 is specified.

### Mobile

- No code change required. The simulator already emits `[72, 99]` for `mvc_value` and `[5, 100]` for `percent_mvc`. The implementation is correct as-is; this ADR ratifies what the code already does.

### Firmware (future)

- Must perform normalization on-device: capture `mvc_baseline_uv` during the calibration test, store it locally, and divide subsequent rep RMS values by it before transmitting.
- Must clamp output to `[0, 100]` (or to the wider Phase-5 range when defined for `percent_mvc`).
- The local `mvc_baseline_uv` is never transmitted; only the resulting `%MVC` values are.

### Documentation

- `contexto.md` (backend design contract) cross-references this ADR.
- `vortex-data-api.md` (mobile integration spec) uses the `%MVC` wording and links here for rationale.

## Compliance check

After applying the changes from this ADR:

- `database/schema.sql` and the corresponding migration enforce `mvc_value > 0 AND mvc_value <= 100`.
- `MvcCalibrationFactory` produces realistic values in `[70, 99]`.
- All 40 backend tests pass.
- `migrate:fresh --seed` wipes pre-ADR data and reseeds only roles.

## References

- `OpenLifting-backend/CLAUDE.md` — backend's role as a sync target.
- `Computacion-movil/CLAUDE.md` — umbrella architecture and offline-first principles.
- Mobile claude session transcript, 2026-05-10: confirmed the simulator already emits `%MVC`, ratified the decision.

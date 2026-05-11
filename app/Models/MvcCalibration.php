<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MvcCalibration extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    /**
     * The 10 (muscle, side) slots, in canonical order. Used by mappers that
     * translate between the API-shape calibrations[] payload and the flat
     * 10 columns in this table.
     */
    public const CALIBRATION_SLOTS = [
        ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT',  'col' => 'vastus_lateralis_left'],
        ['muscle' => 'VASTUS_LATERALIS', 'side' => 'RIGHT', 'col' => 'vastus_lateralis_right'],
        ['muscle' => 'VASTUS_MEDIALIS',  'side' => 'LEFT',  'col' => 'vastus_medialis_left'],
        ['muscle' => 'VASTUS_MEDIALIS',  'side' => 'RIGHT', 'col' => 'vastus_medialis_right'],
        ['muscle' => 'GLUTEUS_MAXIMUS',  'side' => 'LEFT',  'col' => 'gluteus_maximus_left'],
        ['muscle' => 'GLUTEUS_MAXIMUS',  'side' => 'RIGHT', 'col' => 'gluteus_maximus_right'],
        ['muscle' => 'ERECTOR_SPINAE',   'side' => 'LEFT',  'col' => 'erector_spinae_left'],
        ['muscle' => 'ERECTOR_SPINAE',   'side' => 'RIGHT', 'col' => 'erector_spinae_right'],
        ['muscle' => 'BICEPS_FEMORIS',   'side' => 'LEFT',  'col' => 'biceps_femoris_left'],
        ['muscle' => 'BICEPS_FEMORIS',   'side' => 'RIGHT', 'col' => 'biceps_femoris_right'],
    ];

    protected $fillable = [
        'athlete_profile_id',
        'guest_profile_id',
        'vastus_lateralis_left',  'vastus_lateralis_right',
        'vastus_medialis_left',   'vastus_medialis_right',
        'gluteus_maximus_left',   'gluteus_maximus_right',
        'erector_spinae_left',    'erector_spinae_right',
        'biceps_femoris_left',    'biceps_femoris_right',
        'recorded_at',
    ];

    protected function casts(): array
    {
        $casts = ['recorded_at' => 'datetime'];

        foreach (self::CALIBRATION_SLOTS as $slot) {
            $casts[$slot['col']] = 'float';
        }

        return $casts;
    }

    public function athleteProfile(): BelongsTo
    {
        return $this->belongsTo(AthleteProfile::class);
    }

    public function guestProfile(): BelongsTo
    {
        return $this->belongsTo(GuestProfile::class);
    }

    /**
     * Map an API-shape calibrations[] array to column-keyed values, ready
     * for Eloquent fill/update.
     */
    public static function mapCalibrationsToColumns(array $calibrations): array
    {
        $cols = [];
        foreach ($calibrations as $cal) {
            $slot = collect(self::CALIBRATION_SLOTS)
                ->firstWhere(fn ($s) => $s['muscle'] === $cal['muscle'] && $s['side'] === $cal['side']);
            if ($slot === null) {
                continue;
            }
            $cols[$slot['col']] = $cal['mvc_value'];
        }

        return $cols;
    }

    /**
     * Project the flat columns back into the API payload shape:
     * [{muscle, side, mvc_value, recorded_at}, ...]. NULL columns omitted.
     */
    public function calibrationsArray(): array
    {
        $out = [];
        foreach (self::CALIBRATION_SLOTS as $slot) {
            $value = $this->{$slot['col']};
            if ($value === null) {
                continue;
            }
            $out[] = [
                'muscle' => $slot['muscle'],
                'side' => $slot['side'],
                'mvc_value' => (float) $value,
                'recorded_at' => $this->recorded_at,
            ];
        }

        return $out;
    }
}
